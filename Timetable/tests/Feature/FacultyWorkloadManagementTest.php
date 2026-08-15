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
