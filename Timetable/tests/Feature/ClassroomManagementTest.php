<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Services\ClassroomAllocationService;
use App\Services\FacultyAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create and list classrooms', function () {
    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classrooms')
        ->assertStatus(200);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/classrooms', [
        'room_number' => 'A-101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ])->assertRedirect('/admin/classrooms');

    $this->assertDatabaseHas('classrooms', [
        'room_number' => 'A-101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
});

test('admin classroom allocation page loads without demo data', function () {
    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classroom-allocation')
        ->assertOk()
        ->assertSee('Classroom & Lab Allocation')
        ->assertSee('Allocation Results')
        ->assertSee('No allocation records found. Click Auto Generate to start.')
        ->assertDontSee('Configure Division Lab Sharing')
        ->assertDontSee('Save Lab Group');

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classroom-allocation');

    $html = html_entity_decode($response->getContent());
    $firstIndex = mb_strpos($html, 'Classroom & Lab Allocation');
    $secondIndex = mb_strpos($html, 'Allocation Results');

    expect($firstIndex)->not->toBeFalse()
        ->and($secondIndex)->not->toBeFalse()
        ->and($firstIndex)->toBeLessThan($secondIndex);
});

test('auto allocation keeps lab and classroom assignments strictly type-matched and combines lab rooms for higher capacity', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    Classroom::create([
        'room_number' => 'F111',
        'room_capacity' => 80,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'F112',
        'room_capacity' => 80,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'F007',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'F010',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'F011',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);

    Subject::create([
        'name' => 'Application Design & Development using Vibe Coding',
        'subject_code' => 'APP-101',
        'semester' => '5',
        'department_id' => $department->id,
        'credit' => 4,
        'faculty_name' => 'Faculty One',
        'subject_type' => 'Classroom',
    ]);

    Subject::create([
        'name' => 'Computer Hardware and Maintenance',
        'subject_code' => 'CHM-101',
        'semester' => '5',
        'department_id' => $department->id,
        'credit' => 4,
        'faculty_name' => 'Faculty Two',
        'subject_type' => 'Lab',
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/classroom-allocation', ['form_type' => 'auto-allocate'])
        ->assertRedirect('/admin/classroom-allocation');

    $classroomAllocation = RoomAllocation::whereHas('subject', function ($query) {
        $query->where('subject_code', 'APP-101');
    })->first();

    $labAllocation = RoomAllocation::whereHas('subject', function ($query) {
        $query->where('subject_code', 'CHM-101');
    })->first();

    expect($classroomAllocation)->not->toBeNull()
        ->and($classroomAllocation->status)->toBe('Allocated')
        ->and($classroomAllocation->notes)->toBe('F111')
        ->and($labAllocation)->not->toBeNull()
        ->and($labAllocation->status)->toBe('Allocated')
        ->and($labAllocation->notes)->toMatch('/F007 \+ F010|F007 \+ F011|F010 \+ F011/');
});

test('division lab groups share configured rooms and regenerate without stale or duplicate divisions', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $firstLab = Classroom::create([
        'room_number' => 'LAB-A',
        'room_capacity' => 80,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $secondLab = Classroom::create([
        'room_number' => 'LAB-B',
        'room_capacity' => 80,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);

    $subject = Subject::create([
        'name' => 'Information Systems',
        'subject_code' => 'IS-201',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Lab',
        'lecture_credit' => 0,
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => [],
    ]);

    $adminSession = [
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ];

    $this->withSession($adminSession)->post('/admin/classroom-allocation', [
        'form_type' => 'save-lab-group',
        'department_id' => $department->id,
        'semester' => '5',
        'class_name' => 'A',
        'group_name' => 'Morning Lab Group',
        'divisions' => ['A1', 'A2'],
        'classroom_id' => $firstLab->id,
    ])->assertRedirect();

    $this->withSession($adminSession)->post('/admin/classroom-allocation', [
        'form_type' => 'save-lab-group',
        'department_id' => $department->id,
        'semester' => '5',
        'class_name' => 'A',
        'group_name' => 'Afternoon Lab Group',
        'divisions' => ['A3', 'A4'],
        'classroom_id' => $secondLab->id,
    ])->assertRedirect();

    $allocationRequest = [
        'form_type' => 'filtered-auto-allocate',
        'department_id' => $department->id,
        'semester' => '5',
        'class' => 'A',
    ];
    $this->withSession($adminSession)->post('/admin/classroom-allocation', $allocationRequest)
        ->assertRedirect();

    $allocations = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('allocation_type', 'Lab')
        ->orderBy('division')
        ->get();

    expect($allocations)->toHaveCount(4)
        ->and($allocations->pluck('division')->all())->toBe(['A1', 'A2', 'A3', 'A4'])
        ->and($allocations->whereIn('division', ['A1', 'A2'])->pluck('classroom_id')->unique()->all())->toBe([$firstLab->id])
        ->and($allocations->whereIn('division', ['A3', 'A4'])->pluck('classroom_id')->unique()->all())->toBe([$secondLab->id]);

    $secondLab->update(['availability' => 'Maintenance']);
    $this->withSession($adminSession)->post('/admin/classroom-allocation', $allocationRequest)
        ->assertRedirect();
    $unallocatedDivisions = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('allocation_type', 'Lab')
        ->whereIn('division', ['A3', 'A4'])
        ->get();

    expect($unallocatedDivisions)->toHaveCount(2)
        ->and($unallocatedDivisions->every(fn ($allocation): bool => $allocation->status === 'Unallocated'
            && $allocation->classroom_id === null
            && str_contains($allocation->notes, 'Configured lab is unavailable')))->toBeTrue();

    $subject->update(['division_selections' => ['A1', 'A2']]);
    $this->withSession($adminSession)->post('/admin/classroom-allocation', $allocationRequest)
        ->assertRedirect();

    expect(RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('allocation_type', 'Lab')
        ->orderBy('division')
        ->pluck('division')
        ->all())->toBe(['A1', 'A2']);
});

test('unconfigured practical batches automatically share one lab per paired group', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    Classroom::create([
        'room_number' => 'LAB-A',
        'room_capacity' => 80,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'LAB-B',
        'room_capacity' => 80,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    Subject::create([
        'name' => 'Information Systems',
        'subject_code' => 'IS-203',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Lab',
        'lecture_credit' => 0,
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => [],
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/classroom-allocation', [
        'form_type' => 'filtered-auto-allocate',
        'department_id' => $department->id,
        'semester' => '5',
        'class' => 'A',
    ])->assertRedirect();

    $allocations = RoomAllocation::query()
        ->where('department_id', $department->id)
        ->where('allocation_type', 'Lab')
        ->orderBy('division')
        ->get()
        ->keyBy('division');

    expect($allocations)->toHaveCount(4)
        ->and($allocations['A1']->classroom_id)->toBe($allocations['A2']->classroom_id)
        ->and($allocations['A3']->classroom_id)->toBe($allocations['A4']->classroom_id)
        ->and($allocations['A1']->classroom_id)->not->toBe($allocations['A3']->classroom_id);
});

test('auto allocation keeps one classroom per class and distinct paired labs across classes after regeneration', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    foreach ([
        ['room_number' => 'ROOM-A', 'room_type' => 'Classroom'],
        ['room_number' => 'ROOM-B', 'room_type' => 'Classroom'],
        ['room_number' => 'LAB-A1', 'room_type' => 'Lab'],
        ['room_number' => 'LAB-A2', 'room_type' => 'Lab'],
        ['room_number' => 'LAB-B1', 'room_type' => 'Lab'],
        ['room_number' => 'LAB-B2', 'room_type' => 'Lab'],
    ] as $room) {
        Classroom::create([
            ...$room,
            'room_capacity' => 80,
            'availability' => 'Available',
        ]);
    }

    Subject::create([
        'name' => 'Shared Class Subject',
        'subject_code' => 'SHARED-501',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Integrated',
        'lecture_credit' => 1,
        'tutorial_credit' => 1,
        'lab_credit' => 1,
        'class_selections' => ['A', 'B'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4', 'B1', 'B2', 'B3', 'B4'],
        'tutorial_selections' => ['AT1', 'BT1'],
    ]);
    Subject::create([
        'name' => 'D Class Practical',
        'subject_code' => 'D-501',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Practical',
        'lab_credit' => 1,
        'class_selections' => ['D'],
        'division_selections' => ['D1', 'D2', 'D3', 'D4'],
        'tutorial_selections' => [],
    ]);

    $service = new ClassroomAllocationService(new FacultyAllocationService);
    $firstRun = $service->generateAll();
    $firstRunCount = RoomAllocation::count();

    $classroomAllocations = RoomAllocation::query()
        ->where('allocation_type', 'Classroom')
        ->get()
        ->groupBy('class_name');
    $labAllocations = RoomAllocation::query()
        ->where('allocation_type', 'Lab')
        ->get()
        ->groupBy('class_name');

    expect($firstRun['unallocated'])->toBe(0)
        ->and($classroomAllocations['A']->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($classroomAllocations['B']->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($classroomAllocations['A']->first()->classroom_id)->not->toBe($classroomAllocations['B']->first()->classroom_id)
        ->and($labAllocations['A']->whereIn('division', ['A1', 'A2'])->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($labAllocations['A']->whereIn('division', ['A3', 'A4'])->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($labAllocations['A']->whereIn('division', ['A1', 'A2'])->first()->classroom_id)
        ->not->toBe($labAllocations['A']->whereIn('division', ['A3', 'A4'])->first()->classroom_id)
        ->and($labAllocations['B']->whereIn('division', ['B1', 'B2'])->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($labAllocations['B']->whereIn('division', ['B3', 'B4'])->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($labAllocations['B']->whereIn('division', ['B1', 'B2'])->first()->classroom_id)
        ->not->toBe($labAllocations['B']->whereIn('division', ['B3', 'B4'])->first()->classroom_id)
        ->and($labAllocations['A']->pluck('classroom_id')->unique()->intersect($labAllocations['B']->pluck('classroom_id')->unique()))
        ->toBeEmpty()
        ->and($service->classesForSelection($department->id, '5'))->toBe(['A', 'B', 'D'])
        ->and($labAllocations['D']->whereIn('division', ['D1', 'D2'])->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($labAllocations['D']->whereIn('division', ['D3', 'D4'])->pluck('classroom_id')->unique()->count())->toBe(1)
        ->and($labAllocations['D']->whereIn('division', ['D1', 'D2'])->first()->classroom_id)
        ->not->toBe($labAllocations['D']->whereIn('division', ['D3', 'D4'])->first()->classroom_id);

    $secondRun = $service->generateAll();

    expect($secondRun['unallocated'])->toBe(0)
        ->and(RoomAllocation::count())->toBe($firstRunCount)
        ->and($service->classesForSelection($department->id, '5'))->toBe(['A', 'B', 'D']);
});

test('auto allocation reports each practical pair when no suitable labs exist', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    Classroom::create([
        'room_number' => 'ROOM-A',
        'room_capacity' => 80,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Subject::create([
        'name' => 'Practical Without Lab',
        'subject_code' => 'NO-LAB-501',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Practical',
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => [],
    ]);

    $result = (new ClassroomAllocationService(new FacultyAllocationService))->generateAll();
    $allocations = RoomAllocation::query()
        ->where('allocation_type', 'Lab')
        ->get();

    expect($result['unallocated'])->toBe(4)
        ->and(implode(' ', $result['issues']))->toContain('A1/A2')
        ->and(implode(' ', $result['issues']))->toContain('A3/A4')
        ->and($allocations)->toHaveCount(4)
        ->and($allocations->every(fn (RoomAllocation $allocation): bool => $allocation->status === 'Unallocated'
            && $allocation->classroom_id === null
            && str_contains((string) $allocation->notes, 'No available lab')))->toBeTrue();
});

test('allocation results display one existing lab for each practical batch pair', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $subject = Subject::create([
        'name' => 'Information Systems',
        'subject_code' => 'IS-205',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Lab',
        'lecture_credit' => 0,
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2', 'A3', 'A4'],
        'tutorial_selections' => [],
    ]);
    $labs = collect([
        ['room_number' => 'LAB-A', 'room_capacity' => 40, 'room_type' => 'Lab', 'availability' => 'Available'],
        ['room_number' => 'LAB-B', 'room_capacity' => 40, 'room_type' => 'Lab', 'availability' => 'Available'],
        ['room_number' => 'LAB-C', 'room_capacity' => 40, 'room_type' => 'Lab', 'availability' => 'Available'],
        ['room_number' => 'LAB-D', 'room_capacity' => 40, 'room_type' => 'Lab', 'availability' => 'Available'],
    ])->map(fn (array $lab): Classroom => Classroom::create($lab));

    foreach (['A1' => 0, 'A2' => 1, 'A3' => 2, 'A4' => 3] as $division => $labIndex) {
        RoomAllocation::create([
            'department_id' => $department->id,
            'semester' => '5',
            'subject_id' => $subject->id,
            'classroom_id' => $labs[$labIndex]->id,
            'class_name' => 'A',
            'division' => $division,
            'allocation_type' => 'Lab',
            'status' => 'Allocated',
        ]);
    }

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classroom-allocation?department_id='.$department->id.'&semester=5&class=A&subject_id='.$subject->id);

    $response->assertOk()
        ->assertSee('<td>LAB-A</td>', false)
        ->assertSee('<td>LAB-C</td>', false)
        ->assertDontSee('<td>LAB-B</td>', false)
        ->assertDontSee('<td>LAB-D</td>', false);
    expect(substr_count($response->getContent(), '<td>LAB-A</td>'))->toBe(2)
        ->and(substr_count($response->getContent(), '<td>LAB-C</td>'))->toBe(2);
});

test('allocation results show an existing classroom number instead of a lab for lecture rows', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $classroom = Classroom::create([
        'room_number' => 'ROOM-201',
        'room_capacity' => 60,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    $lab = Classroom::create([
        'room_number' => 'LAB-201',
        'room_capacity' => 60,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Algorithms',
        'subject_code' => 'CS-205',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Lecture',
        'lecture_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => [],
        'tutorial_selections' => [],
    ]);
    $allocation = RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '5',
        'subject_id' => $subject->id,
        'classroom_id' => $lab->id,
        'class_name' => 'A',
        'division' => 'A',
        'allocation_type' => 'Classroom',
        'student_count' => 30,
        'status' => 'Allocated',
        'notes' => $lab->room_number,
    ]);

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classroom-allocation?department_id='.$department->id.'&semester=5&class=A&subject_id='.$subject->id);

    $response->assertOk()
        ->assertSee('<td>'.$classroom->room_number.'</td>', false)
        ->assertDontSee('<td>'.$lab->room_number.'</td>', false);

    expect($allocation->fresh()->classroom_id)->toBe($lab->id);
});

test('lab group validation requires configured practical batch pairs to stay together', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $lab = Classroom::create([
        'room_number' => 'LAB-A',
        'room_capacity' => 80,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    Subject::create([
        'name' => 'Information Systems',
        'subject_code' => 'IS-204',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Lab',
        'lecture_credit' => 0,
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => [],
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/classroom-allocation', [
        'form_type' => 'save-lab-group',
        'department_id' => $department->id,
        'semester' => '5',
        'class_name' => 'A',
        'group_name' => 'First practical group',
        'divisions' => ['A1'],
        'classroom_id' => $lab->id,
    ])->assertSessionHasErrors('divisions');

    $this->assertDatabaseCount('lab_allocation_groups', 0);
});

test('filtered auto allocation rejects classes not selected for the department semester in manage subjects', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    Subject::create([
        'name' => 'Information Systems',
        'subject_code' => 'IS-202',
        'semester' => '5',
        'department_id' => $department->id,
        'subject_type' => 'Lab',
        'lecture_credit' => 0,
        'lab_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1'],
        'tutorial_selections' => [],
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classroom-allocation?department_id='.$department->id.'&semester=5')
        ->assertOk()
        ->assertSee('value="A"', false)
        ->assertDontSee('value="B"', false);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/classroom-allocation', [
        'form_type' => 'filtered-auto-allocate',
        'department_id' => $department->id,
        'semester' => '5',
        'class' => 'B',
    ])->assertSessionHasErrors('class');
});
