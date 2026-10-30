<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\Faculty;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetableMetadata;
use App\Models\User;
use App\Services\TimetableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not silently choose another room for an unallocated practical batch', function () {
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
    $classroom = Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $laboratory = Classroom::create([
        'room_number' => 'LAB1',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Practical Without Lab',
        'subject_code' => 'NO-LAB-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => [],
    ]);

    foreach (['A1', 'A2'] as $batch) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $faculty->id,
            'class_name' => 'A',
            'division' => $batch,
            'allocation_type' => 'Lab',
            'student_count' => 20,
            'status' => 'Unallocated',
            'notes' => "No available lab has capacity 20 for practical batch group {$batch}.",
        ]);
    }

    expect(RoomAllocation::query()->get()->every(fn (RoomAllocation $allocation): bool => $allocation->status === 'Unallocated'
        && $allocation->classroom_id === null
        && $allocation->activity_type === null))->toBeTrue();

    expect(fn () => (new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
    ))->toThrow(Exception::class, 'No available lab');
});

it('shows the classroom and laboratory saved on timetable entries when no scoped room allocation matches', function () {
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
    $classroom = Classroom::create([
        'room_number' => 'F101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $laboratory = Classroom::create([
        'room_number' => 'F108',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Database Systems',
        'subject_code' => 'DB-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1'],
    ]);

    foreach ([
        ['type' => 'Lecture', 'classroom' => $classroom, 'slot' => '10:30-11:30', 'duration' => 1],
        ['type' => 'Practical', 'classroom' => $laboratory, 'slot' => '01:00-02:00', 'duration' => 2],
    ] as $session) {
        TimetableEntry::create([
            'department_id' => $department->id,
            'semester' => '5',
            'division' => 'A',
            'academic_year' => '2026-2027',
            'term' => 'Odd',
            'day' => 'Monday',
            'time_slot' => $session['slot'],
            'subject_id' => $subject->id,
            'faculty_id' => $faculty->id,
            'classroom_id' => $session['classroom']->id,
            'lecture_type' => $session['type'],
            'duration' => $session['duration'],
        ]);
    }

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A&academic_year=2026-2027&term=Odd')
        ->assertOk()
        ->assertSee('F101')
        ->assertSee('F108')
        ->assertDontSee('Classroom unavailable')
        ->assertDontSee('Lab unavailable');
});

it('does not use a subject faculty unless it is present in allocation records', function () {
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
    Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Subject::create([
        'name' => 'Lecture Without Allocation',
        'subject_code' => 'NO-ALLOC-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'lecture_credit' => 1,
        'class_selections' => ['A'],
    ]);

    expect(fn () => (new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
        false
    ))->toThrow(Exception::class, 'No faculty is assigned to Lecture');
});

it('does not replace a missing allocated classroom with another available room', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $faculty = Faculty::create([
        'name' => 'Prof. Allocated',
        'email' => 'allocated@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    Classroom::create([
        'room_number' => 'UNASSIGNED-ROOM',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Lecture Without Room',
        'subject_code' => 'NO-ROOM-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'lecture_credit' => 1,
        'class_selections' => ['A'],
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'class_name' => 'A',
        'division' => 'A',
        'allocation_type' => 'Classroom',
        'activity_type' => 'Lecture',
        'student_count' => 30,
        'status' => 'Unallocated',
        'notes' => 'No allocated classroom is available.',
    ]);

    expect(fn () => (new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
        false
    ))->toThrow(Exception::class, 'No allocated classroom is available.');
});

it('prevents a faculty member from being scheduled in another department at an overlapping time', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $otherDepartment = Department::create([
        'name' => 'Electrical Engineering',
        'code' => 'EE',
    ]);
    $faculty = Faculty::create([
        'name' => 'Prof. Shared',
        'email' => 'shared@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $targetRoom = Classroom::create([
        'room_number' => 'CS-ROOM',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $otherRoom = Classroom::create([
        'room_number' => 'EE-ROOM',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Shared Faculty Lecture',
        'subject_code' => 'SHARED-501',
        'semester' => '5',
        'department_id' => $department->id,
        'lecture_credit' => 1,
        'class_selections' => ['A'],
    ]);
    $otherSubject = Subject::create([
        'name' => 'Existing Other Semester Lecture',
        'subject_code' => 'OTHER-301',
        'semester' => '3',
        'department_id' => $otherDepartment->id,
        'lecture_credit' => 1,
        'class_selections' => ['B'],
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'classroom_id' => $targetRoom->id,
        'class_name' => 'A',
        'division' => 'A',
        'allocation_type' => 'Classroom',
        'activity_type' => 'Lecture',
        'student_count' => 30,
        'status' => 'Allocated',
    ]);
    TimetableEntry::create([
        'department_id' => $otherDepartment->id,
        'semester' => '3',
        'division' => 'B',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'day' => 'Monday',
        'time_slot' => '10:30-11:30',
        'subject_id' => $otherSubject->id,
        'faculty_id' => $faculty->id,
        'classroom_id' => $otherRoom->id,
        'lecture_type' => 'Lecture',
        'duration' => 1,
    ]);

    expect((new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
        false
    ))->toBeTrue();

    $generatedEntry = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->firstOrFail();

    expect($generatedEntry->faculty_id)->toBe($faculty->id)
        ->and($generatedEntry->day.'|'.$generatedEntry->time_slot)->not->toBe('Monday|10:30-11:30');
});

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
    $classroom = Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $laboratory = Classroom::create([
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

    foreach ([
        ['batch' => 'A', 'classroom' => $classroom, 'type' => 'Classroom', 'faculty' => $faculty],
        ['batch' => 'A1', 'classroom' => $laboratory, 'type' => 'Lab', 'faculty' => $practicalFaculty],
        ['batch' => 'A2', 'classroom' => $laboratory, 'type' => 'Lab', 'faculty' => $practicalFaculty],
    ] as $allocation) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $allocation['faculty']->id,
            'classroom_id' => $allocation['classroom']->id,
            'class_name' => 'A',
            'division' => $allocation['batch'],
            'allocation_type' => $allocation['type'],
            'student_count' => 20,
            'status' => 'Allocated',
        ]);
    }

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
        ->and($entries->where('lecture_type', 'Practical')->every(
            fn (TimetableEntry $entry): bool => $entry->duration === 2
                && in_array($entry->time_slot, ['10:30-11:30', '01:00-02:00', '03:10-04:10'], true)
        ))->toBeTrue()
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
    $classroom = Classroom::create([
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
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'classroom_id' => $classroom->id,
        'class_name' => 'Computer Science-5-A',
        'division' => 'A',
        'allocation_type' => 'Classroom',
        'activity_type' => 'Minor Project',
        'student_count' => 20,
        'status' => 'Allocated',
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
    Faculty::create([
        'name' => 'Prof. Rao',
        'email' => 'rao@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);
    Faculty::create([
        'name' => 'Prof. Mehta',
        'email' => 'mehta@example.com',
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
        ->and($practicalEntries->pluck('faculty_id')->unique()->count())->toBe(4)
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
    $newClassroom->update(['availability' => 'Occupied']);
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
        ->assertSee('Java Programming')
        ->assertSee('Prof. Shah')
        ->assertSee('ROOM-NEW')
        ->assertSee('South Building')
        ->assertSee('LAB-C')
        ->assertDontSee('ROOM-OLD');

    $theoryAllocation->update(['classroom_id' => $firstLab->id]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A')
        ->assertOk()
        ->assertSee('ROOM-OLD')
        ->assertDontSee('Classroom unavailable')
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
    $classroom = Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $laboratory = Classroom::create([
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

    foreach ([
        ['batch' => 'A', 'classroom' => $classroom, 'type' => 'Classroom', 'faculty' => $originalTheoryFaculty],
        ['batch' => 'A1', 'classroom' => $laboratory, 'type' => 'Lab', 'faculty' => $originalTheoryFaculty],
        ['batch' => 'A2', 'classroom' => $laboratory, 'type' => 'Lab', 'faculty' => $originalTheoryFaculty],
    ] as $allocation) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $allocation['faculty']->id,
            'classroom_id' => $allocation['classroom']->id,
            'class_name' => 'A',
            'division' => $allocation['batch'],
            'allocation_type' => $allocation['type'],
            'student_count' => 20,
            'status' => 'Allocated',
        ]);
    }

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
        ->assertSee('Java Programming')
        ->assertSee('Prof. Patel')
        ->assertSee('(TUT)');

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

test('class-wise batch filtering prevents cross-class practical and tutorial leakage and pairs locations', function (): void {
    $department = Department::create([
        'name' => 'Computer Engineering',
        'code' => 'COMP',
    ]);

    $faculty = Faculty::create([
        'name' => 'Dr. Alpha Lecturer',
        'email' => 'alpha@test.com',
        'department_id' => $department->id,
    ]);
    foreach (['beta', 'gamma', 'delta'] as $name) {
        Faculty::create([
            'name' => 'Dr. '.ucfirst($name).' Instructor',
            'email' => $name.'@test.com',
            'department_id' => $department->id,
        ]);
    }

    $lab1 = Classroom::create([
        'room_number' => 'F108',
        'room_type' => 'Computer Lab',
        'room_capacity' => 40,
        'availability' => 'Available',
    ]);
    $lab2 = Classroom::create([
        'room_number' => 'F102',
        'room_type' => 'Computer Lab',
        'room_capacity' => 40,
        'availability' => 'Available',
    ]);
    $lab3 = Classroom::create([
        'room_number' => 'F104',
        'room_type' => 'Computer Lab',
        'room_capacity' => 40,
        'availability' => 'Available',
    ]);
    $lab4 = Classroom::create([
        'room_number' => 'F106',
        'room_type' => 'Computer Lab',
        'room_capacity' => 40,
        'availability' => 'Available',
    ]);
    $classRoom1 = Classroom::create([
        'room_number' => 'F201',
        'room_type' => 'Classroom',
        'room_capacity' => 60,
        'availability' => 'Available',
    ]);
    $classRoom2 = Classroom::create([
        'room_number' => 'F202',
        'room_type' => 'Classroom',
        'room_capacity' => 60,
        'availability' => 'Available',
    ]);

    $subjectA = Subject::create([
        'department_id' => $department->id,
        'semester' => '5',
        'name' => 'IIOT',
        'subject_code' => 'IIOT301',
        'subject_type' => 'Integrated',
        'credit' => 3,
        'lab_credit' => 2,
        'tutorial_credit' => 1,
        'faculty_id' => $faculty->id,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => ['AT1', 'AT2', 'AT3', 'AT4'],
    ]);

    $subjectB = Subject::create([
        'department_id' => $department->id,
        'semester' => '5',
        'name' => 'Cloud Computing',
        'subject_code' => 'CC302',
        'subject_type' => 'Integrated',
        'credit' => 3,
        'lab_credit' => 2,
        'tutorial_credit' => 1,
        'faculty_id' => $faculty->id,
        'class_selections' => ['B'],
        'division_selections' => ['B1', 'B2', 'B3', 'B4'],
        'tutorial_selections' => ['BT1', 'BT2', 'BT3', 'BT4'],
    ]);

    foreach ([
        ['subject' => $subjectA, 'division' => 'A', 'classroom' => $classRoom1],
        ['subject' => $subjectB, 'division' => 'B', 'classroom' => $classRoom2],
    ] as $allocation) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $allocation['subject']->id,
            'faculty_id' => $faculty->id,
            'classroom_id' => $allocation['classroom']->id,
            'division' => $allocation['division'],
            'allocation_type' => 'Classroom',
            'student_count' => 40,
            'status' => 'Allocated',
        ]);
    }

    // Room allocations for Class A
    // Lab: A1 & A2 -> F108; A3 & A4 -> F102
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subjectA->id,
        'classroom_id' => $lab1->id,
        'division' => 'A1',
        'allocation_type' => 'Lab',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subjectA->id,
        'classroom_id' => $lab2->id,
        'division' => 'A3',
        'allocation_type' => 'Lab',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);

    // Lab: B1 & B2 -> F104; B3 & B4 -> F106
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subjectB->id,
        'classroom_id' => $lab3->id,
        'division' => 'B1',
        'allocation_type' => 'Lab',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subjectB->id,
        'classroom_id' => $lab4->id,
        'division' => 'B3',
        'allocation_type' => 'Lab',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);

    // All tutorials for each class share that class's classroom.
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subjectA->id,
        'classroom_id' => $classRoom1->id,
        'division' => 'AT1',
        'allocation_type' => 'Classroom',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subjectA->id,
        'classroom_id' => $classRoom1->id,
        'division' => 'AT3',
        'allocation_type' => 'Classroom',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);

    // Generate timetables for both A and B
    expect((new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd'))->toBeTrue();
    expect((new TimetableGenerator)->generate($department->id, '5', 'B', '2026-2027', 'Odd'))->toBeTrue();

    // Verify Class A view:
    $responseA = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A');

    $responseA->assertOk();
    $responseA->assertSee('IIOT')
        ->assertSee('Dr. Alpha Lecturer')
        ->assertSee('F108')
        ->assertSee('F102')
        ->assertDontSee('Cloud Computing')
        ->assertDontSee('B1-')
        ->assertDontSee('BT1');
});

it('schedules a tutorial after earlier slots are occupied without changing its assigned faculty', function () {
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
    $room = Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    $subject = Subject::create([
        'name' => 'Advanced Data and Virtual Computing',
        'subject_code' => 'ADDVC',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'tutorial_selections' => ['AT1'],
    ]);

    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'classroom_id' => $room->id,
        'class_name' => 'A',
        'division' => 'AT1',
        'allocation_type' => 'Classroom',
        'activity_type' => 'Tutorial',
        'activity_batch' => 'AT1',
        'student_count' => 30,
        'status' => 'Allocated',
    ]);

    foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day) {
        TimetableEntry::create([
            'department_id' => $department->id,
            'semester' => '5',
            'division' => 'B',
            'academic_year' => '2026-2027',
            'term' => 'Odd',
            'day' => $day,
            'time_slot' => '10:30-11:30',
            'subject_id' => $subject->id,
            'faculty_id' => $faculty->id,
            'classroom_id' => $room->id,
            'lecture_type' => 'Lecture',
            'duration' => 1,
        ]);
    }

    $generator = new TimetableGenerator;
    expect($generator->generate($department->id, '5', 'A', '2026-2027', 'Odd', false))->toBeTrue()
        ->and($generator->generate($department->id, '5', 'A', '2026-2027', 'Odd', false))->toBeTrue();

    $entry = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->firstOrFail();

    expect($entry->faculty_id)->toBe($faculty->id)
        ->and($entry->classroom_id)->toBe($room->id)
        ->and($entry->time_slot)->not->toBe('10:30-11:30')
        ->and(TimetableEntry::query()->where('division', 'B')->count())->toBe(6);
});

it('schedules paired tutorial groups together in their configured shared room', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $firstFaculty = Faculty::create([
        'name' => 'Prof. Shah',
        'email' => 'shah@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $secondFaculty = Faculty::create([
        'name' => 'Prof. Patel',
        'email' => 'patel@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);
    $room = Classroom::create([
        'room_number' => 'TUTORIAL-1',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    $subject = Subject::create([
        'name' => 'Advanced Data and Virtual Computing',
        'subject_code' => 'ADDVC',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $firstFaculty->id,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'tutorial_selections' => ['AT1', 'AT2'],
    ]);

    foreach ([
        ['batch' => 'AT1', 'faculty' => $firstFaculty],
        ['batch' => 'AT2', 'faculty' => $secondFaculty],
    ] as $allocation) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $allocation['faculty']->id,
            'classroom_id' => $room->id,
            'class_name' => 'A',
            'division' => $allocation['batch'],
            'allocation_type' => 'Classroom',
            'activity_type' => 'Tutorial',
            'activity_batch' => $allocation['batch'],
            'student_count' => 20,
            'status' => 'Allocated',
        ]);
    }

    expect((new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd', false))->toBeTrue();

    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->get()
        ->keyBy('activity_batch');

    expect($entries)->toHaveCount(2)
        ->and($entries['AT1']->faculty_id)->toBe($firstFaculty->id)
        ->and($entries['AT2']->faculty_id)->toBe($secondFaculty->id)
        ->and($entries['AT1']->classroom_id)->toBe($room->id)
        ->and($entries['AT2']->classroom_id)->toBe($room->id)
        ->and($entries['AT1']->day.'|'.$entries['AT1']->time_slot)
        ->toBe($entries['AT2']->day.'|'.$entries['AT2']->time_slot);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A&academic_year=2026-2027&term=Odd')
        ->assertSuccessful()
        ->assertSee('Advanced Data and Virtual Computing')
        ->assertSee('Prof. Shah')
        ->assertSee('Prof. Patel')
        ->assertSee('TUTORIAL-1')
        ->assertSee('(TUT)');
});

it('renders scheduled tutorial groups in the existing session format with their allocated rooms', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $faculties = collect([
        'YP' => 'Prof. Y P',
        'B' => 'Prof. Beta',
        'N' => 'Prof. N',
        'P' => 'Prof. P',
        'C' => 'Prof. C',
        'D' => 'Prof. D',
    ])->map(fn (string $name, string $initials): Faculty => Faculty::create([
        'name' => $name,
        'email' => strtolower($initials).'@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]));
    $rooms = collect(['F111', 'F108', 'F112', 'F113'])->mapWithKeys(fn (string $roomNumber): array => [
        $roomNumber => Classroom::create([
            'room_number' => $roomNumber,
            'room_capacity' => 40,
            'room_type' => 'Classroom',
            'availability' => 'Available',
        ]),
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    $addvc = Subject::create([
        'name' => 'Advanced Data and Virtual Computing',
        'subject_code' => 'ADDVC',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculties['YP']->id,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'tutorial_selections' => ['AT1', 'AT2', 'AT3', 'AT4', 'BT1'],
    ]);
    $cloud = Subject::create([
        'name' => 'Cloud Computing',
        'subject_code' => 'CLOUD',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculties['C']->id,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'tutorial_selections' => ['AT1', 'AT2'],
    ]);

    $createTutorialAllocations = function (Subject $subject, array $allocations) use ($department, $faculties, $rooms): void {
        foreach ($allocations as $batch => [$facultyInitials, $roomNumber]) {
            RoomAllocation::create([
                'department_id' => $department->id,
                'semester' => '5',
                'subject_id' => $subject->id,
                'faculty_id' => $faculties[$facultyInitials]->id,
                'classroom_id' => $rooms[$roomNumber]->id,
                'class_name' => 'Computer Science-5-A',
                'division' => 'A',
                'allocation_type' => 'Classroom',
                'activity_type' => 'Tutorial',
                'activity_batch' => $batch,
                'student_count' => 20,
                'status' => 'Allocated',
            ]);
        }
    };

    $createTutorialAllocations($addvc, [
        'AT1' => ['YP', 'F111'],
        'AT2' => ['B', 'F111'],
        'AT3' => ['N', 'F108'],
        'AT4' => ['P', 'F108'],
        'BT1' => ['D', 'F113'],
    ]);
    $createTutorialAllocations($cloud, [
        'AT1' => ['C', 'F112'],
        'AT2' => ['D', 'F113'],
    ]);

    expect((new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd', false))->toBeTrue();

    $generatedEntries = TimetableEntry::query()
        ->where('division', 'A')
        ->where('lecture_type', 'Tutorial')
        ->get()
        ->keyBy(fn (TimetableEntry $entry): string => $entry->subject_id.'|'.$entry->activity_batch);

    expect($generatedEntries[$addvc->id.'|AT1']->classroom_id)->toBe($rooms['F111']->id)
        ->and($generatedEntries[$addvc->id.'|AT2']->classroom_id)->toBe($rooms['F111']->id)
        ->and($generatedEntries[$addvc->id.'|AT3']->classroom_id)->toBe($rooms['F108']->id)
        ->and($generatedEntries[$addvc->id.'|AT4']->classroom_id)->toBe($rooms['F108']->id)
        ->and($generatedEntries->filter(fn (TimetableEntry $entry): bool => $entry->subject_id === $addvc->id)
            ->pluck('day')->unique()->count())->toBe(1)
        ->and($generatedEntries->filter(fn (TimetableEntry $entry): bool => $entry->subject_id === $addvc->id)
            ->pluck('time_slot')->unique()->count())->toBe(1)
        ->and($generatedEntries[$cloud->id.'|AT1']->classroom_id)->toBe($rooms['F112']->id)
        ->and($generatedEntries[$cloud->id.'|AT2']->classroom_id)->toBe($rooms['F113']->id);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A&academic_year=2026-2027&term=Odd')
        ->assertOk()
        ->assertSee('Session Types')
        ->assertSee('ADDVC')
        ->assertSee('Advanced Data and Virtual Computing')
        ->assertSee('Prof. Y P')
        ->assertSee('Prof. Beta')
        ->assertSee('Prof. N')
        ->assertSee('Prof. P')
        ->assertSee('F111')
        ->assertSee('F108')
        ->assertDontSee('BT1')
        ->assertDontSee('AT1-');

    $student = User::create([
        'name' => 'A Student',
        'enrollment_number' => 'CS2026001',
        'email' => 'student@example.com',
        'department' => $department->name,
        'semester' => '5',
        'student_class' => 'A',
        'divcon' => 'A',
        'password' => bcrypt('secret123'),
    ]);

    $this->withSession([
        'student.auth' => [
            'id' => $student->id,
            'department' => $department->name,
            'semester' => '5',
            'student_class' => 'A',
            'divcon' => 'A',
        ],
    ])->get('/student/timetable')
        ->assertOk()
        ->assertSee('Tutorial Legend')
        ->assertSee('ADDVC')
        ->assertSee('Advanced Data and Virtual Computing')
        ->assertSee('AT1')
        ->assertSee('AT2')
        ->assertSee('AT3')
        ->assertSee('AT4')
        ->assertSee('F111')
        ->assertSee('F108')
        ->assertDontSee('BT1');
});

it('shows each student the latest timetable for their semester and class', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $addEntry = function (string $name, string $code, string $semester, string $division, string $academicYear, string $term) use ($department): void {
        $subject = Subject::create([
            'name' => $name,
            'subject_code' => $code,
            'semester' => $semester,
            'department_id' => $department->id,
            'class_selections' => [$division],
        ]);

        TimetableEntry::create([
            'department_id' => $department->id,
            'semester' => $semester,
            'division' => $division,
            'academic_year' => $academicYear,
            'term' => $term,
            'day' => 'Monday',
            'time_slot' => '10:30-11:30',
            'subject_id' => $subject->id,
            'lecture_type' => 'Lecture',
        ]);
    };

    $addEntry('Earlier Schedule', 'EARLIER-B', '3', 'B', '2025-2026', 'Odd');
    $addEntry('Latest Schedule', 'LATEST-B', '3', 'B', '2026-2027', 'Even');
    $addEntry('Wrong Division', 'WRONG-A', '3', 'A', '2026-2027', 'Even');
    $addEntry('Wrong Semester', 'WRONG-SEMESTER', '5', 'B', '2026-2027', 'Even');

    TimetableMetadata::create([
        'department_id' => $department->id,
        'semester' => '3',
        'division' => 'B',
        'academic_year' => '2025-2026',
        'term' => 'Odd',
        'term_code' => '251',
    ]);
    TimetableMetadata::create([
        'department_id' => $department->id,
        'semester' => '3',
        'division' => 'B',
        'academic_year' => '2026-2027',
        'term' => 'Even',
        'term_code' => '262',
    ]);

    $response = $this->withSession([
        'student.auth' => [
            'department' => 'CS',
            'semester' => '3',
            'student_class' => 'CS-B',
            'divcon' => '1',
        ],
    ])->get('/student/timetable')
        ->assertOk()
        ->assertSee('LATEST-B')
        ->assertSee('2026-2027')
        ->assertSee('262')
        ->assertSeeText('Class : 3B')
        ->assertDontSee('WRONG-A')
        ->assertDontSee('WRONG-SEMESTER');

    expect(substr_count($response->getContent(), 'EARLIER-B'))->toBe(1);
});

it('preserves the assigned theory faculty when generating lecture and mandatory tutorial sessions', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $subjectFaculty = Faculty::create([
        'name' => 'Prof. Subject',
        'email' => 'subject@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $allocatedFaculty = Faculty::create([
        'name' => 'Prof. Allocated',
        'email' => 'allocated@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $room = Classroom::create([
        'room_number' => 'TUTORIAL-1',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    $subject = Subject::create([
        'name' => 'Advanced Data and Virtual Computing',
        'subject_code' => 'ADDVC',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $subjectFaculty->id,
        'lecture_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'tutorial_selections' => ['AT1'],
    ]);

    foreach ([
        ['type' => 'Lecture', 'batch' => null],
        ['type' => 'Tutorial', 'batch' => 'AT1'],
    ] as $activity) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $allocatedFaculty->id,
            'classroom_id' => $room->id,
            'class_name' => 'Computer Science-5-A',
            'division' => 'A',
            'allocation_type' => 'Classroom',
            'activity_type' => $activity['type'],
            'activity_batch' => $activity['batch'],
            'student_count' => 30,
            'status' => 'Allocated',
        ]);
    }

    expect((new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
        false
    ))->toBeTrue();

    $entries = TimetableEntry::query()
        ->where('subject_id', $subject->id)
        ->get()
        ->keyBy('lecture_type');

    expect($entries['Lecture']->faculty_id)->toBe($allocatedFaculty->id)
        ->and($entries['Tutorial']->faculty_id)->toBe($allocatedFaculty->id)
        ->and($entries['Lecture']->faculty_id)->not->toBe($subjectFaculty->id);
});

it('reports exact conflicts and preserves the prior timetable when no valid slot exists', function () {
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
    $room = Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    $subject = Subject::create([
        'name' => 'Advanced Data and Virtual Computing',
        'subject_code' => 'ADDVC-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'tutorial_selections' => ['AT1'],
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'classroom_id' => $room->id,
        'class_name' => 'A',
        'division' => 'AT1',
        'allocation_type' => 'Classroom',
        'activity_type' => 'Tutorial',
        'activity_batch' => 'AT1',
        'student_count' => 30,
        'status' => 'Allocated',
    ]);

    $existingTargetEntry = TimetableEntry::create([
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'day' => 'Monday',
        'time_slot' => '10:30-11:30',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'classroom_id' => $room->id,
        'lecture_type' => 'Tutorial',
        'activity_batch' => 'AT1',
        'duration' => 1,
    ]);

    foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day) {
        foreach ([
            '10:30-11:30',
            '11:30-12:30',
            '01:00-02:00',
            '02:00-03:00',
            '03:10-04:10',
            '04:10-05:10',
        ] as $timeSlot) {
            TimetableEntry::create([
                'department_id' => $department->id,
                'semester' => '5',
                'division' => 'B',
                'academic_year' => '2026-2027',
                'term' => 'Odd',
                'day' => $day,
                'time_slot' => $timeSlot,
                'subject_id' => $subject->id,
                'faculty_id' => $faculty->id,
                'classroom_id' => $room->id,
                'lecture_type' => 'Lecture',
                'duration' => 1,
            ]);
        }
    }

    $exception = null;
    try {
        (new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd', false);
    } catch (Exception $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->toContain('ADDVC-501')
        ->toContain('AT1')
        ->toContain('Prof. Shah')
        ->toContain('A101')
        ->toContain('Monday 10:30-11:30')
        ->toContain('Faculty conflict with existing timetable')
        ->toContain('Classroom conflict with existing timetable')
        ->and(TimetableEntry::query()
            ->where('department_id', $department->id)
            ->where('semester', '5')
            ->where('division', 'A')
            ->where('academic_year', '2026-2027')
            ->where('term', 'Odd')
            ->count())->toBe(1)
        ->and($existingTargetEntry->fresh()->time_slot)->toBe('10:30-11:30');
});

it('renders grouped tutorials and a continuous lab block from the selected database timetable', function () {
    $department = Department::create([
        'name' => 'Electrical Engineering',
        'code' => 'EE',
    ]);
    $firstFaculty = Faculty::create([
        'name' => 'Prof. Yash Raj Patel',
        'email' => 'yash@example.com',
        'department_id' => $department->id,
    ]);
    $secondFaculty = Faculty::create([
        'name' => 'Dr. Jaya Nair Anwar',
        'email' => 'jaya@example.com',
        'department_id' => $department->id,
    ]);
    $tutorialRoom = Classroom::create([
        'room_number' => 'F111',
        'room_type' => 'Classroom',
        'room_capacity' => 40,
        'availability' => 'Available',
    ]);
    $laboratory = Classroom::create([
        'room_number' => 'L101',
        'room_type' => 'Laboratory',
        'room_capacity' => 40,
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Advanced Data and Virtual Computing',
        'subject_code' => 'ADDVC',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $firstFaculty->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1'],
        'tutorial_selections' => ['AT1', 'AT2', 'AT3'],
    ]);

    foreach ([
        ['batch' => 'AT1', 'faculty' => $firstFaculty, 'room' => $tutorialRoom],
        ['batch' => 'AT2', 'faculty' => $secondFaculty, 'room' => $tutorialRoom],
    ] as $tutorial) {
        TimetableEntry::create([
            'department_id' => $department->id,
            'semester' => '5',
            'division' => 'A',
            'academic_year' => '2026-2027',
            'term' => 'Odd',
            'day' => 'Tuesday',
            'time_slot' => '10:30-11:30',
            'subject_id' => $subject->id,
            'faculty_id' => $tutorial['faculty']->id,
            'classroom_id' => $tutorial['room']->id,
            'lecture_type' => 'Tutorial',
            'activity_batch' => $tutorial['batch'],
            'duration' => 1,
        ]);
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $tutorial['faculty']->id,
            'classroom_id' => $tutorial['room']->id,
            'class_name' => 'A',
            'division' => $tutorial['batch'],
            'allocation_type' => 'Classroom',
            'activity_type' => 'Tutorial',
            'activity_batch' => $tutorial['batch'],
            'student_count' => 20,
            'status' => 'Allocated',
        ]);
    }

    TimetableEntry::create([
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'day' => 'Tuesday',
        'time_slot' => '11:30-12:30',
        'subject_id' => $subject->id,
        'faculty_id' => $secondFaculty->id,
        'classroom_id' => $tutorialRoom->id,
        'lecture_type' => 'Tutorial',
        'activity_batch' => 'AT3',
        'duration' => 1,
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $secondFaculty->id,
        'classroom_id' => $tutorialRoom->id,
        'class_name' => 'A',
        'division' => 'AT3',
        'allocation_type' => 'Classroom',
        'activity_type' => 'Tutorial',
        'activity_batch' => 'AT3',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);

    TimetableEntry::create([
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
        'academic_year' => '2025-2026',
        'term' => 'Even',
        'day' => 'Tuesday',
        'time_slot' => '10:30-11:30',
        'subject_id' => $subject->id,
        'faculty_id' => $firstFaculty->id,
        'classroom_id' => $tutorialRoom->id,
        'lecture_type' => 'Tutorial',
        'activity_batch' => 'AT4',
        'duration' => 1,
    ]);

    TimetableEntry::create([
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'day' => 'Thursday',
        'time_slot' => '10:30-11:30',
        'subject_id' => $subject->id,
        'faculty_id' => $firstFaculty->id,
        'classroom_id' => $laboratory->id,
        'lecture_type' => 'Practical',
        'activity_batch' => 'A1',
        'duration' => 2,
    ]);
    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $firstFaculty->id,
        'classroom_id' => $laboratory->id,
        'class_name' => 'A',
        'division' => 'A1',
        'allocation_type' => 'Lab',
        'activity_type' => 'Practical',
        'activity_batch' => 'A1',
        'student_count' => 20,
        'status' => 'Allocated',
    ]);

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/timetable', [
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'term_code' => '261',
        'term_dates' => '01/07/2026 to 31/12/2026',
        'wef_date' => '01/07/2026',
        'sbt_dates' => '01/10/2026 to 07/10/2026',
    ])->assertSuccessful();

    $response->assertSee('Department of Electrical Engineering')
        ->assertSee('01/07/2026 to 31/12/2026')
        ->assertSee('Dr. Jaya Nair Anwar')
        ->assertSee('Advanced Data and Virtual Computing')
        ->assertSee('L101')
        ->assertSee('(TUT)')
        ->assertDontSee('A1-ADDVC');

    preg_match(
        '/<td data-day="tuesday" data-time-slot="10:30-11:30" rowspan="1">(.*?)<\/td>/s',
        $response->getContent(),
        $tutorialCell
    );
    preg_match(
        '/<td data-day="tuesday" data-time-slot="11:30-12:30" rowspan="1">(.*?)<\/td>/s',
        $response->getContent(),
        $laterTutorialCell
    );

    expect($tutorialCell)->toHaveCount(2)
        ->and($tutorialCell[1])
        ->toContain('Advanced Data and Virtual Computing')
        ->toContain('Prof. Yash Raj Patel')
        ->toContain('Dr. Jaya Nair Anwar')
        ->toContain('F111')
        ->toContain('(TUT)')
        ->and($laterTutorialCell)->toHaveCount(2)
        ->and($laterTutorialCell[1])
        ->toContain('Advanced Data and Virtual Computing')
        ->toContain('Dr. Jaya Nair Anwar')
        ->toContain('F111')
        ->toContain('(TUT)')
        ->and(TimetableMetadata::query()
            ->where('department_id', $department->id)
            ->where('semester', '5')
            ->where('division', 'A')
            ->where('academic_year', '2026-2027')
            ->where('term', 'Odd')
            ->value('term_dates'))->toBe('01/07/2026 to 31/12/2026')
        ->and($response->getContent())->toContain('Advanced Data and Virtual Computing')
        ->and($response->getContent())->toContain('Prof. Yash Raj Patel')
        ->and($response->getContent())->toContain('L101')
        ->and($response->getContent())->toContain('(PR)')
        ->and($response->getContent())->not->toContain('A1-IIOT')
        ->and($response->getContent())->toContain('data-day="thursday" data-time-slot="10:30-11:30" rowspan="2"');
});

it('generates all paired laboratory batches in one continuous two-hour timetable cell', function () {
    $department = Department::create([
        'name' => 'Electrical Engineering',
        'code' => 'EE',
    ]);
    $faculties = collect([
        'A1' => 'Prof. P J J',
        'A2' => 'Dr. N A P',
        'A3' => 'Mr. M R T',
        'A4' => 'Ms. K M M',
    ])->map(fn (string $name, string $batch): Faculty => Faculty::create([
        'name' => $name,
        'email' => strtolower($batch).'@example.com',
        'department_id' => $department->id,
    ]));
    $laboratories = collect([
        'F108' => Classroom::create([
            'room_number' => 'F108',
            'room_type' => 'Laboratory',
            'room_capacity' => 40,
            'availability' => 'Available',
        ]),
        'F102' => Classroom::create([
            'room_number' => 'F102',
            'room_type' => 'Laboratory',
            'room_capacity' => 40,
            'availability' => 'Available',
        ]),
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    $subject = Subject::create([
        'name' => 'Industrial Internet of Things',
        'subject_code' => 'IIOT',
        'subject_type' => 'Integrated',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculties['A1']->id,
        'lecture_credit' => 0,
        'lab_credit' => 1,
        'tutorial_credit' => 0,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => [],
    ]);

    foreach ([
        'A1' => 'F108',
        'A2' => 'F108',
        'A3' => 'F102',
        'A4' => 'F102',
    ] as $batch => $roomNumber) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'faculty_id' => $faculties[$batch]->id,
            'classroom_id' => $laboratories[$roomNumber]->id,
            'class_name' => 'Legacy class label',
            'division' => null,
            'activity_type' => 'Practical',
            'activity_batch' => $batch,
            'allocation_type' => 'Lab',
            'student_count' => 20,
            'status' => 'Allocated',
        ]);
    }

    expect((new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd', false))->toBeTrue();

    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->where('academic_year', '2026-2027')
        ->where('term', 'Odd')
        ->get()
        ->keyBy('activity_batch');

    expect($entries)->toHaveCount(4)
        ->and($entries->every(fn (TimetableEntry $entry): bool => $entry->duration === 2
            && $entry->day === $entries['A1']->day
            && $entry->time_slot === $entries['A1']->time_slot))->toBeTrue()
        ->and($entries['A1']->classroom_id)->toBe($laboratories['F108']->id)
        ->and($entries['A2']->classroom_id)->toBe($laboratories['F108']->id)
        ->and($entries['A3']->classroom_id)->toBe($laboratories['F102']->id)
        ->and($entries['A4']->classroom_id)->toBe($laboratories['F102']->id);

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A&academic_year=2026-2027&term=Odd')
        ->assertSuccessful()
        ->assertSee('Industrial Internet of Things')
        ->assertSee('Prof. P J J')
        ->assertSee('Dr. N A P')
        ->assertSee('Mr. M R T')
        ->assertSee('Ms. K M M')
        ->assertSee('(PR)')
        ->assertDontSee('Lab unavailable')
        ->assertDontSee('Classroom unavailable')
        ->assertDontSee('A1-IIOT');

    $day = strtolower($entries['A1']->day);
    $slot = preg_quote($entries['A1']->time_slot, '/');
    preg_match(
        '/<td data-day="'.preg_quote($day, '/').'" data-time-slot="'.$slot.'" rowspan="2">(.*?)<\/td>/s',
        $response->getContent(),
        $laboratoryCell
    );

    expect($laboratoryCell)->toHaveCount(2)
        ->and($laboratoryCell[1])
        ->toContain('Industrial Internet of Things')
        ->toContain('Prof. P J J')
        ->toContain('Dr. N A P')
        ->toContain('Mr. M R T')
        ->toContain('Ms. K M M')
        ->toContain('F108')
        ->toContain('F102')
        ->toContain('(PR)')
        ->not->toContain('A1-IIOT');
});
