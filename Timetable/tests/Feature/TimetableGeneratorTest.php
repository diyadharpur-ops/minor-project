<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\Faculty;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Services\TimetableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates conflict-free sessions using the selected class and lab groups', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $faculty = Faculty::create([
        'name' => 'Prof. Shah',
        'email' => 'shah@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    Division::create(['name' => 'A', 'semester' => '5', 'is_active' => true]);
    Classroom::create([
        'room_number' => 'A101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
    Classroom::create([
        'room_number' => 'LAB1',
        'room_capacity' => 40,
        'room_type' => 'Lab',
        'availability' => 'Available',
    ]);
    $subject = Subject::create([
        'name' => 'Java Programming',
        'subject_code' => 'JAVA101',
        'semester' => '5',
        'department_id' => $department->id,
        'faculty_id' => $faculty->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A', 'B'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => ['AT1'],
    ]);

    expect($subject->weekly_hours)->toBe(7)
        ->and($subject->weeklyHoursForClass('A'))->toBe(6);

    expect((new TimetableGenerator)->generate(
        $department->id,
        '5',
        'A',
        '2026-2027',
        'Odd',
    ))->toBeTrue();

    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->get();

    expect($entries)->toHaveCount(4)
        ->and($entries->where('lecture_type', 'Practical'))->toHaveCount(2)
        ->and($entries->sum('duration'))->toBe(6);
});
