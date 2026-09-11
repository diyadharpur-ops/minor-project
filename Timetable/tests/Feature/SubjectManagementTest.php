<?php

use App\Models\Department;
use App\Models\Division;
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

    Division::create(['name' => 'A', 'semester' => '3']);
    Division::create(['name' => 'B', 'semester' => '3']);

    $this->get('/admin/subjects')
        ->assertOk()
        ->assertSee('Semester-wise Weekly Hours Summary')
        ->assertSee('Per Class Weekly Hours: 8 Hours')
        ->assertSee('Selected Classes:')
        ->assertSee('A, B')
        ->assertSee('Total Classes:')
        ->assertSee('2')
        ->assertSee('Total Weekly Hours: 16 Hours')
        ->assertSee('Overall Total Weekly Hours: 16 Hours')
        ->assertSee('>5</td>', false)
        ->assertSee('>3</td>', false);

    $subject = Subject::latest()->first();
    expect($subject->folder_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($subject->folder_path))->toBeTrue();
});

test('subject management shows per-class weekly hours and division totals per semester', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'description' => 'Computer Science Department',
    ]);

    Subject::create([
        'name' => 'Data Structures',
        'subject_code' => 'CS101',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => 0,
    ]);

    Subject::create([
        'name' => 'Algorithms',
        'subject_code' => 'CS102',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 2,
        'lab_credit' => 0,
        'tutorial_credit' => 1,
    ]);

    Division::create(['name' => 'A', 'semester' => '3']);
    Division::create(['name' => 'B', 'semester' => '3']);

    $this->withSession(['admin.auth' => [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]])
        ->get('/admin/subjects')
        ->assertOk()
        ->assertSee('Per Class Weekly Hours: 8 Hours')
        ->assertSee('Selected Classes:')
        ->assertSee('A, B')
        ->assertSee('Total Classes:')
        ->assertSee('2')
        ->assertSee('Total Weekly Hours: 16 Hours')
        ->assertSee('Overall Total Weekly Hours: 16 Hours');
});

test('admin can select a class for a subject and update it later', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'description' => 'Computer Science Department',
    ]);
    $classA = Division::create(['name' => 'A', 'semester' => '3']);
    $classB = Division::create(['name' => 'B', 'semester' => '3']);
    $session = ['admin.auth' => ['name' => 'Admin User', 'email' => 'admin@example.com']];

    $this->withSession($session)->post('/admin/subjects', [
        'name' => 'Operating Systems',
        'subject_code' => 'CS301',
        'semester' => '3',
        'division_id' => $classA->id,
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
    ])->assertRedirect('/admin/subjects');

    $subject = Subject::where('subject_code', 'CS301')->firstOrFail();
    expect($subject->division_id)->toBe($classA->id);

    $this->withSession($session)->post('/admin/subjects/'.$subject->id, [
        'name' => $subject->name,
        'subject_code' => $subject->subject_code,
        'semester' => $subject->semester,
        'division_id' => $classB->id,
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
    ])->assertRedirect('/admin/subjects');

    expect($subject->fresh()->division_id)->toBe($classB->id);
    $this->withSession($session)->get('/admin/subjects')->assertSee('>B</td>', false);
});

test('admin can select class A, B, or C directly from the subject form', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'description' => 'Computer Science Department',
    ]);
    $session = ['admin.auth' => ['name' => 'Admin User', 'email' => 'admin@example.com']];

    $this->withSession($session)
        ->get('/admin/subjects/create')
        ->assertOk()
        ->assertSee('Lecture Weekly Hours')
        ->assertSee('Lab Weekly Hours')
        ->assertSee('Tutorial Weekly Hours')
        ->assertSee('value="A"', false)
        ->assertSee('value="B"', false)
        ->assertSee('value="C"', false);

    $this->withSession($session)->post('/admin/subjects', [
        'name' => 'Compiler Design',
        'subject_code' => 'CS401',
        'semester' => '7',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'division' => 'C',
    ])->assertRedirect('/admin/subjects');

    $subject = Subject::where('subject_code', 'CS401')->firstOrFail();

    expect($subject->division?->name)->toBe('C')
        ->and($subject->weekly_hours)->toBe(6);
});

test('subject management groups only by semester and ignores legacy divisions', function () {
    $department = Department::create([
        'name' => 'Computer Engineering',
        'code' => 'CE',
        'description' => 'Computer Engineering Department',
    ]);
    $division = Division::create(['name' => 'A', 'semester' => '3']);

    $subject = Subject::create([
        'name' => 'Computer Networks',
        'subject_code' => 'CE301',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => 0,
    ]);
    $subject->forceFill(['division_id' => $division->id])->save();

    $session = ['admin.auth' => ['name' => 'Admin User', 'email' => 'admin@example.com']];

    $this->withSession($session)
        ->get('/admin/subjects')
        ->assertOk()
        ->assertSee('Semester 3')
        ->assertSee('Computer Networks')
        ->assertDontSee('Division A')
        ->assertDontSee('>Division</th>', false);

    $this->withSession($session)
        ->get('/admin/subjects?q=Computer+Engineering')
        ->assertOk()
        ->assertSee('Computer Networks')
        ->assertDontSee('Division A');
});
