<?php

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create and manage faculty workload records with automatic calculation and filtering', function () {
    $department = Department::create([
        'name' => 'Computer Engineering',
        'code' => 'CE',
        'hod_name' => 'Prof. Mehta',
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-workload')
        ->assertOk()
        ->assertSee('Faculty Workload Management')
        ->assertSee('No Faculty Workload Data Yet');

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/faculty-workload', [
        'faculty_name' => 'Rahul Patel',
        'faculty_id' => 'FAC001',
        'department' => 'Computer Engineering',
        'subjects_assigned' => 'DBMS, OS',
        'theory_hours' => 12,
        'practical_hours' => 8,
        'assigned_classes' => 'CE-5A, CE-5B',
        'free_periods' => '6',
    ]);

    $response->assertRedirect('/admin/faculty-workload');

    $this->assertDatabaseHas('faculty_workloads', [
        'faculty_name' => 'Rahul Patel',
        'faculty_id' => 'FAC001',
        'department' => 'Computer Engineering',
        'subjects_assigned' => 'DBMS, OS',
        'theory_hours' => 12,
        'practical_hours' => 8,
        'total_hours' => 20,
        'workload_status' => 'Overloaded',
        'assigned_classes' => 'CE-5A, CE-5B',
        'free_periods' => '6',
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-workload?q=Rahul&status=Overloaded')
        ->assertOk()
        ->assertSee('Rahul Patel')
        ->assertSee('FAC001')
        ->assertSee('Overloaded');

    $workload = \App\Models\FacultyWorkload::first();

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-workload/'.$workload->id)
        ->assertOk()
        ->assertSee('Rahul Patel')
        ->assertSee('20')
        ->assertSee('Overloaded');
});

test('admin can auto generate faculty workload from department allocation data', function () {
    $department = Department::create([
        'name' => 'Computer Engineering',
        'code' => 'CE',
        'hod_name' => 'Prof. Mehta',
    ]);

    $faculty = \App\Models\Faculty::create([
        'name' => 'Dr. Neha Joshi',
        'email' => 'neha@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);

    $subject = \App\Models\Subject::create([
        'name' => 'Database Management',
        'subject_code' => 'DBMS101',
        'semester' => '3',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'lecture_credit' => 3,
        'lab_credit' => 2,
        'tutorial_credit' => 1,
    ]);

    \App\Models\RoomAllocation::create([
        'department_id' => $department->id,
        'semester' => '3',
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'class_name' => 'CE-3A',
        'status' => 'Allocated',
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/faculty-workload/generate', [
        'department_id' => $department->id,
    ])->assertRedirect('/admin/faculty-workload?department_id='.$department->id)
        ->assertSessionHas('success', 'Faculty workload generated successfully.');

    $this->assertDatabaseHas('faculty_workloads', [
        'faculty_name' => 'Dr. Neha Joshi',
        'faculty_id' => (string) $faculty->id,
        'department' => 'Computer Engineering',
        'semester' => '3',
        'subject_name' => 'Database Management',
        'subject_code' => 'DBMS101',
        'weekly_hours' => 8,
        'weekly_workload' => 8,
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-workload?department_id='.$department->id)
        ->assertOk()
        ->assertSee('Faculty Workload Details')
        ->assertSee('Dr. Neha Joshi')
        ->assertSee('Database Management');
});
