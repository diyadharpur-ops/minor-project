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
