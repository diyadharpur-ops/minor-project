<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\FacultyWorkload;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\FacultyAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('faculty allocation service uses real project data and subject metadata', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $faculty = Faculty::create([
        'name' => 'Dr. R. Sharma',
        'email' => 'dr.sharma@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);

    $subject = Subject::create([
        'name' => 'Operating Systems',
        'subject_code' => 'CS-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'subject_type' => 'Theory',
        'lecture_credit' => 3,
        'lab_credit' => 0,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'tutorial_selections' => ['AT1'],
    ]);

    User::create([
        'name' => 'Asha Patel',
        'email' => 'asha@example.com',
        'password' => bcrypt('secret123'),
        'enrollment_number' => 'CS2025001',
        'department' => 'Computer Science',
        'semester' => '5',
        'divcon' => 'A',
    ]);

    FacultyWorkload::create([
        'faculty_name' => $faculty->name,
        'faculty_id' => (string) $faculty->id,
        'department' => $department->name,
        'subjects_assigned' => $subject->name,
        'theory_hours' => 10,
        'practical_hours' => 0,
        'total_hours' => 10,
        'workload_status' => 'Normal',
    ]);

    $service = new FacultyAllocationService;
    $batches = $service->batches();

    expect($batches)->toHaveCount(3)
        ->and($batches->pluck('department_name')->unique()->all())->toBe(['Computer Science'])
        ->and($batches->pluck('semester')->unique()->all())->toBe(['5'])
        ->and($batches->pluck('name')->unique()->sort()->values()->all())->toBe(['A', 'B', 'C'])
        ->and($subject->fresh()->subject_type)->toBe('Theory')
        ->and($subject->fresh()->weekly_hours)->toBe(4);
});

test('faculty allocation service balances workload across faculty for the same semester', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $facultyA = Faculty::create([
        'name' => 'Dr. A. Mehta',
        'email' => 'a.mehta@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);

    $facultyB = Faculty::create([
        'name' => 'Dr. B. Shah',
        'email' => 'b.shah@example.com',
        'designation' => 'Associate Professor',
        'department_id' => $department->id,
    ]);

    $facultyC = Faculty::create([
        'name' => 'Dr. C. Patel',
        'email' => 'c.patel@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);

    foreach ([
        ['Operating Systems', 'CS-501', 4],
        ['Database Systems', 'CS-502', 4],
        ['Compiler Design', 'CS-503', 4],
        ['Software Engineering', 'CS-504', 4],
    ] as [$name, $code, $hours]) {
        Subject::create([
            'name' => $name,
            'subject_code' => $code,
            'semester' => '5',
            'department_id' => $department->id,
            'subject_type' => 'Theory',
            'lecture_credit' => $hours,
            'lab_credit' => 0,
            'tutorial_credit' => 0,
            'class_selections' => ['A'],
        ]);
    }

    $service = new FacultyAllocationService;
    $selection = ['department_id' => $department->id, 'semester' => '5', 'batch' => $department->id.'|5|A'];
    $result = $service->generateForSelection($selection);

    $groupedLoads = RoomAllocation::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('class_name', $department->name.'-5-A')
        ->with('subject')
        ->get()
        ->groupBy('faculty_id')
        ->map(fn ($allocations) => $allocations->sum(fn ($allocation) => (int) ($allocation->subject?->weekly_hours ?? 0)))
        ->values()
        ->all();

    expect($result['count'])->toBe(4)
        ->and($groupedLoads)->toHaveCount(3)
        ->and(max($groupedLoads) - min($groupedLoads))->toBeLessThanOrEqual(4);
});

test('faculty allocation assigns mandatory practical and tutorial groups without a lecture credit', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $faculty = Faculty::create([
        'name' => 'Dr. Practical Faculty',
        'email' => 'practical.faculty@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);
    $subject = Subject::create([
        'name' => 'Applied Programming',
        'subject_code' => 'CS-302',
        'semester' => '3',
        'department_id' => $department->id,
        'subject_type' => 'Practical',
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1'],
        'tutorial_selections' => ['AT1'],
    ]);

    $result = (new FacultyAllocationService)->generateForSelection([
        'department_id' => $department->id,
        'semester' => '3',
        'division' => 'A',
    ]);
    $allocations = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->get()
        ->keyBy('activity_type');

    expect($result['count'])->toBe(2)
        ->and($result['warnings'])->toBeEmpty()
        ->and($allocations->get('Practical')->faculty_id)->toBe($faculty->id)
        ->and($allocations->get('Tutorial')->faculty_id)->toBe($faculty->id);
});

test('faculty allocation assigns minor projects from the existing faculty roster and saves assignments idempotently', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $faculty = Faculty::create([
        'name' => 'Dr. Project Faculty',
        'email' => 'project.faculty@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
        'subjects' => 'CS-590',
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
    $selection = [
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
        '_token' => 'test-token',
    ];
    $session = [
        '_token' => 'test-token',
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ];

    $this->withSession($session)
        ->post('/admin/faculty-allocation/generate', $selection)
        ->assertRedirect()
        ->assertSessionHas('faculty_allocation_status', 'Auto allocation completed: assigned faculty to 1 subject activities.');

    $allocation = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('class_name', 'Computer Science-5-A')
        ->firstOrFail();

    expect($allocation->activity_type)->toBe('Minor Project')
        ->and($allocation->faculty_id)->toBe($faculty->id)
        ->and($subject->weekly_hours)->toBe(2)
        ->and($subject->weeklyHoursForClass('A'))->toBe(2)
        ->and(Faculty::count())->toBe(1);

    $this->withSession($session)
        ->post('/admin/faculty-allocation/generate', $selection)
        ->assertRedirect();

    expect(RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('class_name', 'Computer Science-5-A')
        ->count())->toBe(1)
        ->and(RoomAllocation::findOrFail($allocation->id)->faculty_id)->toBe($faculty->id);

    $this->withSession($session)
        ->get('/admin/faculty-allocation?department_id='.$department->id.'&semester=5&division=A')
        ->assertOk()
        ->assertSee('Auto Allocate Faculty')
        ->assertSee('Dr. Project Faculty')
        ->assertSee('Minor Project')
        ->assertDontSee('Theory faculty required')
        ->assertDontSee('Select faculty')
        ->assertDontSee('name="faculty_id"');
});

test('faculty allocation respects workload limits and falls back when a subject expert is overloaded', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $expert = Faculty::create([
        'name' => 'Dr. Subject Expert',
        'email' => 'subject.expert@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
        'subjects' => 'CS-501',
    ]);
    $availableFaculty = Faculty::create([
        'name' => 'Dr. Available',
        'email' => 'available@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
        'subjects' => 'CS-999',
    ]);
    FacultyWorkload::create([
        'faculty_name' => $expert->name,
        'faculty_id' => (string) $expert->id,
        'department' => $department->name,
        'subjects_assigned' => 'Existing subjects',
        'weekly_workload' => 17,
        'total_hours' => 17,
        'workload_status' => 'Normal',
    ]);
    $subject = Subject::create([
        'name' => 'Operating Systems',
        'subject_code' => 'CS-501',
        'semester' => '5',
        'department_id' => $department->id,
        'lecture_credit' => 2,
        'class_selections' => ['A'],
    ]);

    $result = (new FacultyAllocationService)->generateForSelection([
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
    ]);
    $allocation = RoomAllocation::query()->where('subject_id', $subject->id)->firstOrFail();

    expect($result['count'])->toBe(1)
        ->and($allocation->faculty_id)->toBe($availableFaculty->id)
        ->and($allocation->faculty_id)->not->toBe($expert->id);
});

test('faculty allocation assigns theory faculty to A1 and AT1 and fairly distributes remaining groups', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $theoryFaculty = Faculty::create([
        'name' => 'Dr. Theory',
        'email' => 'theory@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);

    $otherFaculty = collect(['B', 'C', 'D'])->map(fn (string $name): Faculty => Faculty::create([
        'name' => "Dr. {$name}",
        'email' => strtolower($name).'@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]));

    $subject = Subject::create([
        'name' => 'Data Structures',
        'subject_code' => 'CS-301',
        'semester' => '3',
        'department_id' => $department->id,
        'faculty_id' => $theoryFaculty->id,
        'lecture_credit' => 2,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => ['AT1', 'AT2'],
    ]);

    $result = (new FacultyAllocationService)->generateForSelection([
        'department_id' => $department->id,
        'semester' => '3',
        'division' => 'A',
    ]);

    $assignments = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('class_name', 'Computer Science-3-A')
        ->get()
        ->keyBy(fn (RoomAllocation $allocation): string => $allocation->activity_type.'|'.($allocation->activity_batch ?? ''));

    expect($result['warnings'])->toBeEmpty()
        ->and($assignments)->toHaveCount(7)
        ->and($assignments->get('Lecture|')->faculty_id)->toBe($theoryFaculty->id)
        ->and($assignments->get('Practical|A1')->faculty_id)->toBe($theoryFaculty->id)
        ->and($assignments->get('Tutorial|AT1')->faculty_id)->toBe($theoryFaculty->id)
        ->and($assignments->get('Practical|A2')->faculty_id)->not->toBe($theoryFaculty->id)
        ->and($assignments->get('Practical|A3')->faculty_id)->not->toBe($theoryFaculty->id)
        ->and($assignments->get('Practical|A4')->faculty_id)->not->toBe($theoryFaculty->id)
        ->and($assignments->get('Tutorial|AT2')->faculty_id)->not->toBe($theoryFaculty->id);

    $alternativeLoadCounts = $assignments
        ->filter(fn (RoomAllocation $allocation): bool => $allocation->activity_batch !== null
            && ! in_array($allocation->faculty_id, [$theoryFaculty->id, null], true))
        ->countBy('faculty_id')
        ->values();

    expect($alternativeLoadCounts->max() - $alternativeLoadCounts->min())->toBeLessThanOrEqual(1)
        ->and($otherFaculty->pluck('id'))->toContain($assignments->get('Practical|A2')->faculty_id);

    $administratorFacultyId = $otherFaculty->last()->id;
    $assignments->get('Practical|A2')->update(['faculty_id' => $administratorFacultyId]);
    (new FacultyAllocationService)->generateForSelection([
        'department_id' => $department->id,
        'semester' => '3',
        'division' => 'A',
    ]);

    expect($assignments->get('Practical|A2')->fresh()->faculty_id)->toBe($administratorFacultyId);
});

test('faculty allocation preserves mandatory assignments and warns when there is no eligible alternative', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $theoryFaculty = Faculty::create([
        'name' => 'Dr. Theory',
        'email' => 'theory@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $subject = Subject::create([
        'name' => 'Data Structures',
        'subject_code' => 'CS-301',
        'semester' => '3',
        'department_id' => $department->id,
        'faculty_id' => $theoryFaculty->id,
        'lecture_credit' => 2,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => ['AT1', 'AT2'],
    ]);

    $result = (new FacultyAllocationService)->generateForSelection([
        'department_id' => $department->id,
        'semester' => '3',
        'division' => 'A',
    ]);
    $assignments = RoomAllocation::query()->where('subject_id', $subject->id)->get()
        ->keyBy(fn (RoomAllocation $allocation): string => $allocation->activity_type.'|'.($allocation->activity_batch ?? ''));

    expect($assignments->get('Practical|A1')->faculty_id)->toBe($theoryFaculty->id)
        ->and($assignments->get('Tutorial|AT1')->faculty_id)->toBe($theoryFaculty->id)
        ->and($assignments->get('Practical|A2')->faculty_id)->toBeNull()
        ->and($assignments->get('Tutorial|AT2')->faculty_id)->toBeNull()
        ->and($result['warnings']->join(' '))->toContain('No eligible faculty within workload limits');
});

test('faculty allocation warns when mandatory theory faculty has no available practical or tutorial slot', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $theoryFaculty = Faculty::create([
        'name' => 'Dr. Theory',
        'email' => 'theory@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $subject = Subject::create([
        'name' => 'Data Structures',
        'subject_code' => 'CS-301',
        'semester' => '3',
        'department_id' => $department->id,
        'faculty_id' => $theoryFaculty->id,
        'lecture_credit' => 2,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1'],
        'tutorial_selections' => ['AT1'],
    ]);

    foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day) {
        foreach (['10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10'] as $timeSlot) {
            TimetableEntry::create([
                'department_id' => $department->id,
                'semester' => '3',
                'division' => 'B',
                'academic_year' => '2026-2027',
                'term' => 'Odd',
                'day' => $day,
                'time_slot' => $timeSlot,
                'subject_id' => $subject->id,
                'faculty_id' => $theoryFaculty->id,
                'lecture_type' => 'Lecture',
                'duration' => 1,
            ]);
        }
    }

    $result = (new FacultyAllocationService)->generateForSelection([
        'department_id' => $department->id,
        'semester' => '3',
        'division' => 'A',
    ]);
    $assignments = RoomAllocation::query()->where('subject_id', $subject->id)->get()
        ->keyBy(fn (RoomAllocation $allocation): string => $allocation->activity_type.'|'.($allocation->activity_batch ?? ''));

    expect($assignments->get('Practical|A1')->faculty_id)->toBe($theoryFaculty->id)
        ->and($assignments->get('Tutorial|AT1')->faculty_id)->toBe($theoryFaculty->id)
        ->and($result['warnings']->join(' '))->toContain('conflicts with the theory faculty\'s existing timetable');
});

test('admin faculty allocation page derives semester and division options from available batches and shows lecture lab tutorial details', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $faculty = Faculty::create([
        'name' => 'Dr. R. Sharma',
        'email' => 'dr.sharma@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);

    $subject = Subject::create([
        'name' => 'Operating Systems',
        'subject_code' => 'CS-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'subject_type' => 'Theory',
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1'],
        'tutorial_selections' => ['AT1'],
    ]);

    User::create([
        'name' => 'Asha Patel',
        'email' => 'asha@example.com',
        'password' => bcrypt('secret123'),
        'enrollment_number' => 'CS2025001',
        'department' => 'Computer Science',
        'semester' => '5',
        'divcon' => 'A',
    ]);

    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'class_name' => 'Computer Science-5-A',
        'status' => 'Allocated',
        'notes' => 'Room-101',
    ]);

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-allocation?department_id='.$department->id.'');

    $response->assertOk();
    $response->assertSee('Semester 5');
    $response->assertSee('Class / Division');
    $response->assertSee('A');
    $response->assertSee('B');
    $response->assertSee('C');
    $response->assertSee('Lecture');
    $response->assertSee('Lab');
    $response->assertSee('Tutorial');
    $response->assertSeeInOrder([
        'Operating Systems',
        'Lecture',
        'Dr. R. Sharma',
        '3 Hours',
        'Classroom',
        'Operating Systems',
        'Lab',
        'Dr. R. Sharma',
        '2 Hours',
        'Lab',
        'Operating Systems',
        'Tutorial',
        'Dr. R. Sharma',
        '1 Hour',
        'Classroom',
    ]);
});

test('faculty allocation service creates class batches for subject-only data when division is selected', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $faculty = Faculty::create([
        'name' => 'Dr. R. Sharma',
        'email' => 'dr.sharma@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);

    Subject::create([
        'name' => 'Operating Systems',
        'subject_code' => 'CS-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'subject_type' => 'Theory',
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
    ]);

    $service = new FacultyAllocationService;
    $batches = $service->batches();

    expect($batches->pluck('name')->filter()->unique()->values()->sort()->values()->all())->toBe(['5', 'A', 'B', 'C'])
        ->and($service->batchesForSelection([
            'department_id' => $department->id,
            'semester' => '5',
            'division' => 'A',
        ])->pluck('class_name')->all())->toBe(['Computer Science-5-A']);
});

test('admin faculty allocation page renders selected batch allocations without array property errors', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $faculty = Faculty::create([
        'name' => 'Dr. R. Sharma',
        'email' => 'dr.sharma@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);

    $subject = Subject::create([
        'name' => 'Operating Systems',
        'subject_code' => 'CS-501',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'subject_type' => 'Theory',
        'lecture_credit' => 3,
        'lab_credit' => 0,
        'tutorial_credit' => 1,
    ]);

    User::create([
        'name' => 'Asha Patel',
        'email' => 'asha@example.com',
        'password' => bcrypt('secret123'),
        'enrollment_number' => 'CS2025001',
        'department' => 'Computer Science',
        'semester' => '5',
        'divcon' => 'A',
    ]);

    RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'class_name' => 'Computer Science-5-A',
        'status' => 'Allocated',
        'notes' => 'Room-101',
    ]);

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-allocation?department_id='.$department->id.'&semester=5&batch='.urlencode($department->id.'|5|A'));

    $response->assertOk();
    $response->assertSee('Operating Systems');
    $response->assertSee('Dr. R. Sharma');
    $response->assertSee('A');
    $response->assertSee('B');
    $response->assertSee('C');
});
