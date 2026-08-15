<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create and view faculty workload records using real faculty and subject data', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'hod_name' => 'Prof. Rao',
    ]);

    $faculty = Faculty::create([
        'name' => 'Dr. A. Verma',
        'designation' => 'Associate Professor',
        'email' => 'verma@example.com',
        'password' => 'secret123',
        'department_id' => $department->id,
        'subjects' => 'Algorithms',
    ]);

    $subject = Subject::create([
        'name' => 'Data Structures',
        'subject_code' => 'CS201',
        'semester' => '5',
        'department_id' => $department->id,
        'credit' => 4,
        'faculty_name' => 'Dr. A. Verma',
        'subject_type' => 'lecture',
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-workload')
        ->assertOk()
        ->assertSee('Faculty Workload Management');

    $response = $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/faculty-workload', [
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'subject_id' => $subject->id,
        'subject_type' => 'Theory',
        'semester' => '5',
        'class_name' => 'CS-A',
        'division' => 'A',
        'theory_hours' => 6,
        'practical_hours' => 2,
        'assigned_classes' => 'CS-A, CS-B',
        'free_periods' => 'Tuesday 1st slot',
        'timetable_id' => null,
    ]);

    $response->assertRedirect('/admin/faculty-workload');

    $this->assertDatabaseHas('faculty_workloads', [
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'subject_id' => $subject->id,
        'subject_type' => 'Theory',
        'semester' => '5',
        'class_name' => 'CS-A',
        'division' => 'A',
        'theory_hours' => 6,
        'practical_hours' => 2,
        'assigned_classes' => 'CS-A, CS-B',
        'free_periods' => 'Tuesday 1st slot',
    ]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/faculty-workload')
        ->assertOk()
        ->assertSee('Dr. A. Verma')
        ->assertSee('8')
        ->assertSee('Normal');
});
