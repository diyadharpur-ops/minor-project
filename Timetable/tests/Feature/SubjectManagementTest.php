<?php

use App\Models\Department;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create and list subjects with lecture and lab credits while leaving tutorial credit optional', function () {
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
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => '',
    ]);

    $response->assertRedirect('/admin/subjects');

    $this->assertDatabaseHas('subjects', [
        'name' => 'Data Structures',
        'subject_code' => 'CS101',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => null,
    ]);

    $this->withSession(['admin.auth' => [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]])->post('/admin/subjects', [
        'name' => 'Algorithms',
        'subject_code' => 'CS102',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 2,
        'lab_credit' => 0,
        'tutorial_credit' => 1,
    ])->assertRedirect('/admin/subjects');

    $this->get('/admin/subjects')
        ->assertOk()
        ->assertSee('Semester-wise Weekly Hours Summary')
        ->assertSee('Total Weekly Hours: 8 Hours')
        ->assertSee('>5</td>', false)
        ->assertSee('>3</td>', false);

    $subject = Subject::latest()->first();
    expect($subject->folder_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($subject->folder_path))->toBeTrue();
});
