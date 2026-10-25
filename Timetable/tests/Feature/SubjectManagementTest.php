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
        'selection_groups_present' => 1,
        'class_selections' => ['A', 'B'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => [],
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
        'selection_groups_present' => 1,
        'class_selections' => ['A', 'B'],
        'division_selections' => [],
        'tutorial_selections' => ['AT1', 'AT2'],
    ])->assertRedirect('/admin/subjects');

    Division::create(['name' => 'A', 'semester' => '3']);
    Division::create(['name' => 'B', 'semester' => '3']);

    $this->get('/admin/subjects')
        ->assertOk()
        ->assertSee('Semester-wise Weekly Hours Summary')
        ->assertSee('Selected Classes:')
        ->assertSee('A, B')
        ->assertSee('Total Classes:')
        ->assertSee('2')
        ->assertSee('Total Lecture Credits:')
        ->assertSee('data-field="total-lecture-credits">10</span>', false)
        ->assertSee('Total Lab Credits:')
        ->assertSee('data-field="total-lab-credits">2</span>', false)
        ->assertSee('Total Tutorial Credits:')
        ->assertSee('data-field="total-tutorial-credits">2</span>', false)
        ->assertSee('data-field="total-hours">16 Hours</span>', false)
        ->assertSee('Overall Total Weekly Hours: 16 Hours')
        ->assertSee('>6</td>', false)
        ->assertSee('>4</td>', false);

    $subject = Subject::latest()->first();
    expect($subject->folder_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($subject->folder_path))->toBeTrue();
});

test('calculates all weekly credits and hours from the selected groups', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $this->withSession(['admin.auth' => [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]])->post('/admin/subjects', [
        'name' => 'Selected Group Calculations',
        'subject_code' => 'CS103',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 1,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'selection_groups_present' => 1,
        'class_selections' => ['A', 'B'],
        'division_selections' => ['A1', 'A2'],
        'tutorial_selections' => ['AT1', 'BT1'],
    ])->assertRedirect('/admin/subjects');

    $subject = Subject::where('subject_code', 'CS103')->firstOrFail();

    expect($subject->total_lecture_credits)->toBe(2)
        ->and($subject->total_lab_credits)->toBe(2)
        ->and($subject->total_tutorial_credits)->toBe(2)
        ->and($subject->lecture_weekly_hours)->toBe(2)
        ->and($subject->lab_weekly_hours)->toBe(4)
        ->and($subject->tutorial_weekly_hours)->toBe(2)
        ->and($subject->weekly_hours)->toBe(8)
        ->and($subject->weeklyHoursForClass('A'))->toBe(6)
        ->and($subject->weeklyHoursForClass('B'))->toBe(2);

    $this->withSession(['admin.auth' => [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]])->get('/admin/subjects')
        ->assertOk()
        ->assertSee('data-field="total-lecture-credits">2</span>', false)
        ->assertSee('data-field="total-lab-credits">2</span>', false)
        ->assertSee('data-field="total-tutorial-credits">2</span>', false)
        ->assertSee('data-field="total-hours">8 Hours</span>', false);
});

test('infers classes only from saved group selections when the class list is missing', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $subject = Subject::create([
        'name' => 'Existing Group Configuration',
        'subject_code' => 'CS104',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
        'division_selections' => ['A1', 'A2', 'B1', 'B2'],
        'tutorial_selections' => ['AT1', 'BT1'],
    ]);

    expect($subject->effective_class_selections)->toBe(['A', 'B'])
        ->and($subject->total_lecture_credits)->toBe(6)
        ->and($subject->total_lab_credits)->toBe(4)
        ->and($subject->total_tutorial_credits)->toBe(2)
        ->and($subject->weekly_hours)->toBe(16);
});

test('does not assume classes for existing subjects without saved selections', function () {
    $department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);
    $subject = Subject::create([
        'name' => 'Unconfigured Existing Subject',
        'subject_code' => 'CS105',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 3,
        'lab_credit' => 1,
        'tutorial_credit' => 1,
    ]);

    expect($subject->effective_class_selections)->toBe([])
        ->and($subject->class_selection_label)->toBe('Not specified')
        ->and($subject->weekly_hours)->toBe(0);
});

test('subject management totals calculated credits and hours once per semester', function () {
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
        'class_selections' => ['A', 'B'],
        'division_selections' => ['A1', 'B1'],
    ]);

    Subject::create([
        'name' => 'Algorithms',
        'subject_code' => 'CS102',
        'semester' => '3',
        'department_id' => $department->id,
        'lecture_credit' => 2,
        'lab_credit' => 0,
        'tutorial_credit' => 1,
        'class_selections' => ['A', 'B'],
        'division_selections' => [],
        'tutorial_selections' => ['AT1', 'BT1'],
    ]);

    Division::create(['name' => 'A', 'semester' => '3']);
    Division::create(['name' => 'B', 'semester' => '3']);

    $this->withSession(['admin.auth' => [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]])
        ->get('/admin/subjects')
        ->assertOk()
        ->assertSee('Selected Classes:')
        ->assertSee('A, B')
        ->assertSee('Total Classes:')
        ->assertSee('2')
        ->assertSee('data-field="total-lecture-credits">10</span>', false)
        ->assertSee('data-field="total-lab-credits">2</span>', false)
        ->assertSee('data-field="total-tutorial-credits">2</span>', false)
        ->assertSee('data-field="total-hours">16 Hours</span>', false)
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
        ->assertSee('Lecture Credit per Selected Class')
        ->assertSee('Lab Credit per Selected Division')
        ->assertSee('Tutorial Credit per Selected Group')
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
        ->and($subject->weekly_hours)->toBe(3);
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
        'class_selections' => ['A'],
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
