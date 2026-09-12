<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\FacultyWorkload;
use App\Models\Subject;
use App\Models\User;
use App\Services\FacultyAllocationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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

    $service = new FacultyAllocationService();
    $batches = $service->batches();

    expect($batches)->toHaveCount(1)
        ->and($batches[0]['department_name'])->toBe('Computer Science')
        ->and($batches[0]['semester'])->toBe('5')
        ->and($batches[0]['name'])->toBe('A')
        ->and($subject->fresh()->subject_type)->toBe('Theory')
        ->and($subject->fresh()->weekly_hours)->toBe(4);
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

    \App\Models\RoomAllocation::create([
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
