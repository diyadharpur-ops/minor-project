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

    // Tutorials: AT1 & AT2 -> F201; AT3 & AT4 -> F202
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
        'classroom_id' => $classRoom2->id,
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
    // Class A batches and tutorial groups must be present
    $responseA->assertSee('A1-IIOT');
    $responseA->assertSee('A2-IIOT');
    $responseA->assertSee('A3-IIOT');
    $responseA->assertSee('A4-IIOT');
    // Class A lab paired locations: A1 and A2 share F108, A3 and A4 share F102
    expect(preg_match('/A1-IIOT-[^<]+-F108/', $responseA->getContent()))->toBe(1)
        ->and(preg_match('/A2-IIOT-[^<]+-F108/', $responseA->getContent()))->toBe(1)
        ->and(preg_match('/A3-IIOT-[^<]+-F102/', $responseA->getContent()))->toBe(1)
        ->and(preg_match('/A4-IIOT-[^<]+-F102/', $responseA->getContent()))->toBe(1);

    // Class B batches must never appear in Class A's timetable
    $responseA->assertDontSee('B1-');
    $responseA->assertDontSee('B2-');
    $responseA->assertDontSee('B3-');
    $responseA->assertDontSee('B4-');
    $responseA->assertDontSee('BT1');
    $responseA->assertDontSee('BT2');
    $responseA->assertDontSee('BT3');
    $responseA->assertDontSee('BT4');
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
        ->assertSee('AT1-^ADDVC-Prof. Shah-TUTORIAL-1')
        ->assertSee('AT2-^ADDVC-Prof. Patel-TUTORIAL-1')
        ->assertSee('(TUTORIAL)');
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
        ->and($generatedEntries[$cloud->id.'|AT1']->classroom_id)->toBe($rooms['F112']->id)
        ->and($generatedEntries[$cloud->id.'|AT2']->classroom_id)->toBe($rooms['F113']->id);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A&academic_year=2026-2027&term=Odd')
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
        ->assertSee('A1-ADDVC-Prof. Yash Raj Patel-L101');

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
        ->toContain('AT1-^ADDVC-Prof. Yash Raj Patel-F111')
        ->toContain('AT2-^ADDVC-Dr. Jaya Nair Anwar-F111')
        ->not->toContain('AT3-^ADDVC')
        ->not->toContain('AT4-^ADDVC')
        ->and($laterTutorialCell)->toHaveCount(2)
        ->and($laterTutorialCell[1])->toContain('AT3-^ADDVC-Dr. Jaya Nair Anwar-F111')
        ->and(TimetableMetadata::query()
            ->where('department_id', $department->id)
            ->where('semester', '5')
            ->where('division', 'A')
            ->where('academic_year', '2026-2027')
            ->where('term', 'Odd')
            ->value('term_dates'))->toBe('01/07/2026 to 31/12/2026')
        ->and(substr_count($response->getContent(), 'A1-ADDVC-Prof. Yash Raj Patel-L101'))->toBe(1)
        ->and($response->getContent())->toContain('(LAB)')
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
        ->assertSee('A1-IIOT-Prof. P J J-F108')
        ->assertSee('A2-IIOT-Dr. N A P-F108')
        ->assertSee('A3-IIOT-Mr. M R T-F102')
        ->assertSee('A4-IIOT-Ms. K M M-F102')
        ->assertSee('(LAB)')
        ->assertDontSee('Lab unavailable')
        ->assertDontSee('Classroom unavailable');

    $day = strtolower($entries['A1']->day);
    $slot = preg_quote($entries['A1']->time_slot, '/');
    preg_match(
        '/<td data-day="'.preg_quote($day, '/').'" data-time-slot="'.$slot.'" rowspan="2">(.*?)<\/td>/s',
        $response->getContent(),
        $laboratoryCell
    );

    expect($laboratoryCell)->toHaveCount(2)
        ->and($laboratoryCell[1])
        ->toContain('A1-IIOT-Prof. P J J-F108')
        ->toContain('A2-IIOT-Dr. N A P-F108')
        ->toContain('A3-IIOT-Mr. M R T-F102')
        ->toContain('A4-IIOT-Ms. K M M-F102')
        ->toContain('(LAB)');
});
