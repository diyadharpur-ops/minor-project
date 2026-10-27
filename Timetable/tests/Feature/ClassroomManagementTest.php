<?php

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
        ->assertSee('No allocation records found. Click Auto Generate to start.');

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classroom-allocation');

    $html = $response->getContent();
    $firstIndex = mb_strpos($html, 'Classroom & Lab Allocation');
    $secondIndex = mb_strpos($html, 'Allocation Results');

    expect($firstIndex)->not->toBeFalse()
        ->and($secondIndex)->not->toBeFalse()
        ->and($firstIndex)->toBeLessThan($secondIndex);
});

test('auto allocation keeps lab and classroom assignments strictly type-matched and combines lab rooms for higher capacity', function () {
    $department = \App\Models\Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    \App\Models\Classroom::create([
        'room_number' => 'F111',
        'room_capacity' => 80,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    \App\Models\Classroom::create([
        'room_number' => 'F112',
        'room_capacity' => 80,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    \App\Models\Classroom::create([
        'room_number' => 'F007',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    \App\Models\Classroom::create([
        'room_number' => 'F010',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    \App\Models\Classroom::create([
        'room_number' => 'F011',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);

    \App\Models\Subject::create([
        'name' => 'Application Design & Development using Vibe Coding',
        'subject_code' => 'APP-101',
        'semester' => '5',
        'department_id' => $department->id,
        'credit' => 4,
        'faculty_name' => 'Faculty One',
        'subject_type' => 'Classroom',
    ]);

    \App\Models\Subject::create([
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

    $classroomAllocation = \App\Models\RoomAllocation::whereHas('subject', function ($query) {
        $query->where('subject_code', 'APP-101');
    })->first();

    $labAllocation = \App\Models\RoomAllocation::whereHas('subject', function ($query) {
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
    $department = \App\Models\Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $firstLab = \App\Models\Classroom::create([
        'room_number' => 'LAB-A',
        'room_capacity' => 80,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $secondLab = \App\Models\Classroom::create([
        'room_number' => 'LAB-B',
        'room_capacity' => 80,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);

    $subject = \App\Models\Subject::create([
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

    $allocations = \App\Models\RoomAllocation::query()
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
    $unallocatedDivisions = \App\Models\RoomAllocation::query()
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

    expect(\App\Models\RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('allocation_type', 'Lab')
        ->orderBy('division')
        ->pluck('division')
        ->all())->toBe(['A1', 'A2']);
});

test('filtered auto allocation rejects classes not selected for the department semester in manage subjects', function () {
    $department = \App\Models\Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    \App\Models\Subject::create([
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
