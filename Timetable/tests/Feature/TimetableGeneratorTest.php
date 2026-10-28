<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\Faculty;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Services\TimetableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates conflict-free sessions using the selected class and lab groups', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $faculty = Faculty::create([
        'name' => 'Prof. Shah',
        'email' => 'shah@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $practicalFaculty = Faculty::create([
        'name' => 'Prof. Patel',
        'email' => 'patel@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'LAB1',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Java Programming',
        'subject_code' => 'JAVA101',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A', 'B'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => ['AT1'],
    ]);

    expect($subject->weekly_hours)->toBe(7)
        ->and($subject->weeklyHoursForClass('A'))->toBe(6);

    expect((new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
    ))->toBeTrue();

    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->get();

    expect($entries)->toHaveCount(4)
        ->and($entries->where('lecture_type', 'Practical'))->toHaveCount(2)
        ->and($entries->sum('duration'))->toBe(6)
        ->and($entries->firstWhere('activity_batch', 'A1')->faculty_id)->toBe($faculty->id)
        ->and($entries->firstWhere('activity_batch', 'A2')->faculty_id)->toBe($practicalFaculty->id)
        ->and($entries->firstWhere('activity_batch', 'A1')->classroom_id)
        ->toBe($entries->firstWhere('activity_batch', 'A2')->classroom_id);
});

it('schedules automatically allocated minor project sessions as conflict-free classroom activities', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $faculty = Faculty::create([
        'name' => 'Prof. Project',
        'email' => 'project@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
        'subjects' => 'CS-590',
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Minor Project',
        'subject_code' => 'CS-590',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Minor Project',
        'credit' => 2,
        'class_selections' => ['A'],
    ]);

    expect((new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
    ))->toBeTrue();

    $allocations = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('class_name', 'Computer Science-5-A')
        ->get();
    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->get();

    expect($allocations)->toHaveCount(1)
        ->and($allocations->first()->activity_type)->toBe('Minor Project')
        ->and($allocations->first()->faculty_id)->toBe($faculty->id)
        ->and($entries)->toHaveCount(2)
        ->and($entries->every(fn (TimetableEntry $entry): bool => $entry->lecture_type === 'Minor Project'
            && $entry->faculty_id === $faculty->id
            && $entry->classroom_id !== null))->toBeTrue();
});

it('uses configured theory and paired lab locations and reflects allocation changes in the timetable view', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $theoryFaculty = Faculty::create([
        'name' => 'Prof. Shah',
        'email' => 'shah@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    Faculty::create([
        'name' => 'Prof. Patel',
        'email' => 'patel@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    $oldClassroom = Classroom::create([
        'room_number' => 'ROOM-OLD',
        'location' => 'North Building',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $newClassroom = Classroom::create([
        'room_number' => 'ROOM-NEW',
        'location' => 'South Building',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $firstLab = Classroom::create([
        'room_number' => 'LAB-A',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $secondLab = Classroom::create([
        'room_number' => 'LAB-B',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $replacementLab = Classroom::create([
        'room_number' => 'LAB-C',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Java Programming',
        'subject_code' => 'JAVA102',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $theoryFaculty->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'tutorial_credit' => 0,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => [],
    ]);

    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $theoryFaculty->id,
        'classroom_id' => $oldClassroom->id,
        'class_name' => 'A',
        'division' => 'A',
        'allocation_type' => 'Classroom',
        'student_count' => 30,
        'status' => 'Allocated',
    ]);
    foreach ([
        'A1' => $firstLab,
        'A2' => $secondLab,
        'A3' => $secondLab,
        'A4' => $firstLab,
    ] as $batch => $lab) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $theoryFaculty->id,
            'classroom_id' => $lab->id,
            'class_name' => 'A',
            'division' => $batch,
            'allocation_type' => 'Lab',
            'student_count' => 20,
            'status' => 'Allocated',
        ]);
    }

    expect((new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd'))->toBeTrue();

    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->get();
    $practicalEntries = $entries->where('lecture_type', 'Practical')->keyBy('activity_batch');

    expect($entries->firstWhere('lecture_type', 'Lecture')->classroom_id)->toBe($oldClassroom->id)
        ->and($practicalEntries['A1']->classroom_id)->toBe($firstLab->id)
        ->and($practicalEntries['A2']->classroom_id)->toBe($firstLab->id)
        ->and($practicalEntries['A3']->classroom_id)->toBe($secondLab->id)
        ->and($practicalEntries['A4']->classroom_id)->toBe($secondLab->id);

    RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('allocation_type', 'Classroom')
        ->where('division', 'A')
        ->firstOrFail()
        ->update(['classroom_id' => $newClassroom->id]);
    RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('allocation_type', 'Lab')
        ->where('division', 'A1')
        ->firstOrFail()
        ->update(['classroom_id' => $replacementLab->id]);
    $theoryAllocation = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('allocation_type', 'Classroom')
        ->where('division', 'A')
        ->firstOrFail();

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A')
        ->assertOk()
        ->assertSee('Classroom: ROOM-NEW - South Building')
        ->assertSee('Lab: LAB-C')
        ->assertDontSee('ROOM-OLD');

    $theoryAllocation->update(['classroom_id' => $firstLab->id]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A')
        ->assertOk()
        ->assertSee('Classroom unavailable')
        ->assertDontSee('Classroom: LAB-A');
});

it('refreshes an existing timetable after faculty allocations are regenerated', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $originalTheoryFaculty = Faculty::create([
        'name' => 'Prof. Shah',
        'email' => 'shah@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $replacementTheoryFaculty = Faculty::create([
        'name' => 'Prof. Patel',
        'email' => 'patel@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    Faculty::create([
        'name' => 'Prof. Rao',
        'email' => 'rao@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);
    Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'LAB1',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Java Programming',
        'subject_code' => 'JAVA101',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $originalTheoryFaculty->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => ['AT1', 'AT2'],
    ]);

    expect((new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd'))->toBeTrue();
    $subject->update(['faculty_id' => $replacementTheoryFaculty->id]);

    $this->withSession([
        '_token' => 'test-token',
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/faculty-allocation/generate', [
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
        '_token' => 'test-token',
    ])->assertRedirect('/admin/faculty-allocation?department_id='.$department->id.'&semester=5&division=A');

    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->get();

    expect($entries->where('lecture_type', 'Lecture')->first()->faculty_id)->toBe($replacementTheoryFaculty->id)
        ->and($entries->firstWhere('activity_batch', 'A1')->faculty_id)->toBe($replacementTheoryFaculty->id)
        ->and($entries->firstWhere('activity_batch', 'AT1')->faculty_id)->toBe($replacementTheoryFaculty->id);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A')
        ->assertOk()
        ->assertSee('A1')
        ->assertSee('AT1');

    $practicalAllocation = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('activity_type', 'Practical')
        ->where('activity_batch', 'A2')
        ->firstOrFail();
    $manualFaculty = Faculty::query()
        ->where('department_id', $department->id)
        ->whereNotIn('id', [$replacementTheoryFaculty->id, $practicalAllocation->faculty_id])
        ->firstOrFail();

    $this->withSession([
        '_token' => 'test-token',
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/faculty-allocation/'.$practicalAllocation->id.'/faculty', [
        'faculty_id' => $manualFaculty->id,
        '_token' => 'test-token',
    ])->assertRedirect('/admin/faculty-allocation?department_id='.$department->id.'&semester=5&division=A&batch='.urlencode($department->id.'|5|A'));

    expect($practicalAllocation->fresh()->faculty_id)->toBe($manualFaculty->id)
        ->and(TimetableEntry::query()
            ->where('subject_id', $subject->id)
            ->where('activity_batch', 'A2')
            ->first()->faculty_id)->toBe($manualFaculty->id);
});
