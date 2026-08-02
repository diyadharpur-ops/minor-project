<?php

use App\Models\Department;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create and list subjects in department and semester folders', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'description' => 'Computer Science Department',
    ]);

    $response = $this->withSession(['admin.auth' => [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]])->post('/admin/subjects', [
        'name' => 'Data Structures',
        'subject_code' => 'CS101',
        'semester' => '3',
        'department_id' => $department->id,
        'credit' => 3,
        'faculty_name' => 'Dr. Jane Smith',
        'subject_type' => 'lecture',
    ]);

    $response->assertRedirect('/admin/subjects');

    $this->assertDatabaseHas('subjects', [
        'name' => 'Data Structures',
        'subject_code' => 'CS101',
        'semester' => '3',
        'department_id' => $department->id,
        'credit' => 3,
        'faculty_name' => 'Dr. Jane Smith',
        'subject_type' => 'lecture',
    ]);

    $this->get('/admin/subjects')->assertOk();

    $subject = Subject::latest()->first();
    expect($subject->folder_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($subject->folder_path))->toBeTrue();
});
