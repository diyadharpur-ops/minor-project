<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\Faculty;
use App\Models\RoomAllocation;
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
    $practicalFaculty = Faculty::create([
        'name' => 'Prof. Patel',
        'email' => 'patel@example.com',
        'designation' => 'Assistant Professor',
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
        ->and($entries->sum('duration'))->toBe(6)
        ->and($entries->firstWhere('activity_batch', 'A1')->faculty_id)->toBe($faculty->id)
        ->and($entries->firstWhere('activity_batch', 'A2')->faculty_id)->toBe($practicalFaculty->id);
});

it('refreshes an existing timetable after faculty allocations are regenerated', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $originalTheoryFaculty = Faculty::create([
        'name' => 'Prof. Shah',
        'email' => 'shah@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    $replacementTheoryFaculty = Faculty::create([
        'name' => 'Prof. Patel',
        'email' => 'patel@example.com',
        'designation' => 'Professor',
        'department_id' => $department->id,
    ]);
    Faculty::create([
        'name' => 'Prof. Rao',
        'email' => 'rao@example.com',
        'designation' => 'Assistant Professor',
        'department_id' => $department->id,
    ]);
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
        'faculty_id' => $originalTheoryFaculty->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'class_selections' => ['A'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => ['AT1', 'AT2'],
    ]);

    expect((new TimetableGenerator)->generate($department->id, '5', 'A', '2026-2027', 'Odd'))->toBeTrue();
    $subject->update(['faculty_id' => $replacementTheoryFaculty->id]);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/faculty-allocation/generate', [
        'department_id' => $department->id,
        'semester' => '5',
        'division' => 'A',
    ])->assertRedirect('/admin/faculty-allocation?department_id='.$department->id.'&semester=5&division=A');

    $entries = TimetableEntry::query()
        ->where('department_id', $department->id)
        ->where('semester', '5')
        ->where('division', 'A')
        ->get();

    expect($entries->where('lecture_type', 'Lecture')->first()->faculty_id)->toBe($replacementTheoryFaculty->id)
        ->and($entries->firstWhere('activity_batch', 'A1')->faculty_id)->toBe($replacementTheoryFaculty->id)
        ->and($entries->firstWhere('activity_batch', 'AT1')->faculty_id)->toBe($replacementTheoryFaculty->id);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/timetable?department_id='.$department->id.'&semester=5&division=A')
        ->assertOk()
        ->assertSee('A1')
        ->assertSee('AT1');

    $practicalAllocation = RoomAllocation::query()
        ->where('subject_id', $subject->id)
        ->where('activity_type', 'Practical')
        ->where('activity_batch', 'A2')
        ->firstOrFail();
    $manualFaculty = Faculty::query()
        ->where('department_id', $department->id)
        ->whereNotIn('id', [$replacementTheoryFaculty->id, $practicalAllocation->faculty_id])
        ->firstOrFail();

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/faculty-allocation/'.$practicalAllocation->id.'/faculty', [
        'faculty_id' => $manualFaculty->id,
    ])->assertRedirect('/admin/faculty-allocation?department_id='.$department->id.'&semester=5&division=A&batch='.urlencode($department->id.'|5|A'));

    expect($practicalAllocation->fresh()->faculty_id)->toBe($manualFaculty->id)
        ->and(TimetableEntry::query()
            ->where('subject_id', $subject->id)
            ->where('activity_batch', 'A2')
            ->first()->faculty_id)->toBe($manualFaculty->id);
});
