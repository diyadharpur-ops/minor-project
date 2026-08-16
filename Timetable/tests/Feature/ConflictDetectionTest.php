<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('detects real timetable conflicts from project data', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'hod_name' => 'Dr. Sharma',
    ]);

    $facultyA = Faculty::create([
        'name' => 'Prof. Alpha',
        'email' => 'alpha@example.com',
        'designation' => 'Professor',
        'password' => 'secret123',
        'department_id' => $department->id,
    ]);

    $facultyB = Faculty::create([
        'name' => 'Prof. Beta',
        'email' => 'beta@example.com',
        'designation' => 'Professor',
        'password' => 'secret123',
        'department_id' => $department->id,
    ]);

    $subject = Subject::create([
        'name' => 'Algorithms',
        'subject_code' => 'CS101',
        'semester' => '6',
        'department_id' => $department->id,
        'credit' => 4,
        'faculty_name' => 'Prof. Alpha',
        'subject_type' => 'lecture',
    ]);

    $classroomA = Classroom::create([
        'room_number' => 'A-101',
        'room_capacity' => 60,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);

    $lab = Classroom::create([
        'room_number' => 'Lab-01',
        'room_capacity' => 30,
        'room_type' => 'Computer Lab',
        'availability' => 'Available',
    ]);

    TimetableEntry::create([
        'department_id' => $department->id,
        'semester' => '6',
        'division' => 'A',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'day' => 'Monday',
        'time_slot' => '09:30-10:30',
        'subject_id' => $subject->id,
        'faculty_id' => $facultyA->id,
        'classroom_id' => $classroomA->id,
        'lecture_type' => 'lecture',
        'duration' => 1,
    ]);

    TimetableEntry::create([
        'department_id' => $department->id,
        'semester' => '6',
        'division' => 'A',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'day' => 'Monday',
        'time_slot' => '09:30-10:30',
        'subject_id' => $subject->id,
        'faculty_id' => $facultyB->id,
        'classroom_id' => $lab->id,
        'lecture_type' => 'lab',
        'duration' => 1,
    ]);

    TimetableEntry::create([
        'department_id' => $department->id,
        'semester' => '6',
        'division' => 'B',
        'academic_year' => '2026-2027',
        'term' => 'Odd',
        'day' => 'Monday',
        'time_slot' => '09:30-10:30',
        'subject_id' => $subject->id,
        'faculty_id' => $facultyA->id,
        'classroom_id' => $classroomA->id,
        'lecture_type' => 'lecture',
        'duration' => 1,
    ]);

    session(['admin.auth' => ['name' => 'Admin User', 'email' => 'admin@example.com']]);

    $response = $this->post('/admin/conflicts');

    $response->assertOk();
    $response->assertSee('Faculty Conflict');
    $response->assertSee('Classroom Conflict');
    $response->assertSee('Lab Conflict');
    $response->assertSee('Same Subject Duplicate Slot');
});
