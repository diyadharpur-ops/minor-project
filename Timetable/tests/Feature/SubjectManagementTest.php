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

test('subject class, division, and tutorial selections persist independently through editing and deletion', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'description' => 'Computer Science Department',
    ]);
    $session = ['admin.auth' => ['name' => 'Admin User', 'email' => 'admin@example.com']];

    $this->withSession($session)->post('/admin/subjects', [
        'name' => 'Distributed Systems',
        'subject_code' => 'CS501',
        'semester' => '5',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'selection_groups_present' => 1,
        'class_selections' => ['A', 'C'],
        'division_selections' => ['A1', 'C4'],
        'tutorial_selections' => ['AT2', 'CT1'],
    ])->assertRedirect('/admin/subjects');

    $subject = Subject::where('subject_code', 'CS501')->firstOrFail();
    expect($subject->class_selections)->toBe(['A', 'C'])
        ->and($subject->division_selections)->toBe(['A1', 'C4'])
        ->and($subject->tutorial_selections)->toBe(['AT2', 'CT1']);

    $this->withSession($session)
        ->get('/admin/subjects/'.$subject->id.'/edit')
        ->assertOk()
        ->assertSee('name="class_selections[]" value="A" checked', false)
        ->assertSee('name="division_selections[]" value="A1" checked', false)
        ->assertSee('name="tutorial_selections[]" value="AT2" checked', false);

    $this->withSession($session)->post('/admin/subjects/'.$subject->id, [
        'name' => 'Distributed Systems',
        'subject_code' => 'CS501',
        'semester' => '5',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'selection_groups_present' => 1,
        'class_selections' => ['B'],
        'division_selections' => ['B4'],
        'tutorial_selections' => ['BT3'],
    ])->assertRedirect('/admin/subjects');

    $subject->refresh();
    expect($subject->class_selections)->toBe(['B'])
        ->and($subject->division_selections)->toBe(['B4'])
        ->and($subject->tutorial_selections)->toBe(['BT3']);

    Division::create(['name' => 'B', 'semester' => '5', 'is_active' => true]);

    $this->withSession($session)->get('/admin/subjects')
        ->assertOk()
        ->assertSee('B4')
        ->assertSee('BT3')
        ->assertSee('Distributed Systems')
        ->assertSee('Per Class Weekly Hours: 5 Hours')
        ->assertSee('Total Weekly Hours: 5 Hours');

    $recordPath = $subject->folder_path;
    $this->withSession($session)
        ->post('/admin/subjects/'.$subject->id.'/delete')
        ->assertRedirect('/admin/subjects');

    $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    expect(Storage::disk('local')->exists($recordPath))->toBeFalse();
});

test('division and tutorial selections on the manage subjects page save independently', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'description' => 'Computer Science Department',
    ]);
    Division::create(['name' => 'A', 'semester' => '3', 'is_active' => true]);
    $subject = Subject::create([
        'name' => 'Database Systems',
        'subject_code' => 'CS303',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
    ]);
    $session = ['admin.auth' => ['name' => 'Admin User', 'email' => 'admin@example.com']];

    $this->withSession($session)
        ->get('/admin/subjects')
        ->assertOk()
        ->assertSee('data-assignment-type="division"', false)
        ->assertSee('data-selection="A1"', false)
        ->assertSee('data-assignment-type="tutorial"', false)
        ->assertSee('data-selection="AT1"', false)
        ->assertDontSee('type="checkbox" disabled', false);

    $this->withSession($session)->postJson('/admin/subjects/selection-toggle', [
        'semester' => '3',
        'assignment_type' => 'division',
        'selection' => 'A1',
        'is_selected' => true,
    ])->assertOk()->assertJson(['success' => true]);

    $this->withSession($session)->postJson('/admin/subjects/selection-toggle', [
        'semester' => '3',
        'assignment_type' => 'tutorial',
        'selection' => 'AT1',
        'is_selected' => true,
    ])->assertOk()->assertJson(['success' => true]);

    $subject->refresh();
    expect($subject->division_selections)->toBe(['A1'])
        ->and($subject->tutorial_selections)->toBe(['AT1']);

    $this->withSession($session)->postJson('/admin/subjects/selection-toggle', [
        'semester' => '3',
        'assignment_type' => 'division',
        'selection' => 'A1',
        'is_selected' => false,
    ])->assertOk();

    expect($subject->fresh()->division_selections)->toBe([])
        ->and($subject->fresh()->tutorial_selections)->toBe(['AT1']);

    $this->withSession($session)->get('/admin/subjects')
        ->assertOk()
        ->assertSee('data-assignment-type="tutorial"', false)
        ->assertSee('data-selection="AT1"', false)
        ->assertSee('checked', false);

    $this->withSession($session)->postJson('/admin/divisions/toggle', [
        'semester' => '3',
        'division' => 'A',
        'is_active' => false,
    ])->assertOk();

    expect($subject->fresh()->division_selections)->toBe([])
        ->and($subject->fresh()->tutorial_selections)->toBe([]);
});

test('subject selections reject children from classes that were not selected', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
        'description' => 'Computer Science Department',
    ]);

    $this->from('/admin/subjects/create')
        ->withSession(['admin.auth' => ['name' => 'Admin User', 'email' => 'admin@example.com']])
        ->post('/admin/subjects', [
            'name' => 'Secure Programming',
            'subject_code' => 'CS502',
            'semester' => '5',
            'department_id' => $department->id,
            'lecture_credit' => 3,
            'lab_credit' => 0,
            'selection_groups_present' => 1,
            'class_selections' => ['A'],
            'division_selections' => ['B1'],
            'tutorial_selections' => ['AT1'],
        ])
        ->assertRedirect('/admin/subjects/create')
        ->assertSessionHasErrors('division_selections');

    $this->assertDatabaseMissing('subjects', ['subject_code' => 'CS502']);
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
