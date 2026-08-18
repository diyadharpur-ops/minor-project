<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminSession = [
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ];
});

test('admin can access notifications dashboard and see statistics', function () {
    Notification::create([
        'title' => 'Test High',
        'description' => 'Test high alert',
        'priority' => 'High',
        'category' => 'System',
        'status' => 'Unread',
    ]);

    Notification::create([
        'title' => 'Test Info',
        'description' => 'Test info alert',
        'priority' => 'Info',
        'category' => 'General',
        'status' => 'Read',
    ]);

    $response = $this->withSession($this->adminSession)
        ->get('/admin/notifications');

    $response->assertStatus(200)
        ->assertSee('Test High')
        ->assertSee('Test Info')
        ->assertSee('Total Notifications')
        ->assertSee('High Priority');
});

test('admin can filter notifications by priority or status via AJAX', function () {
    Notification::create([
        'title' => 'High Alarm',
        'description' => 'This is high priority',
        'priority' => 'High',
        'category' => 'Timetable',
        'status' => 'Unread',
    ]);

    Notification::create([
        'title' => 'Info Announcement',
        'description' => 'This is low priority',
        'priority' => 'Info',
        'category' => 'General',
        'status' => 'Unread',
    ]);

    // AJAX Filter High
    $response = $this->withSession($this->adminSession)
        ->get('/notifications/filter/High', ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertSee('High Alarm')
        ->assertDontSee('Info Announcement');
});

test('admin can mark notification as read via AJAX', function () {
    $notif = Notification::create([
        'title' => 'Read Alert',
        'description' => 'Click to read',
        'priority' => 'Info',
        'category' => 'General',
        'status' => 'Unread',
    ]);

    $response = $this->withSession($this->adminSession)
        ->post("/notifications/{$notif->id}/read", [], ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('notifications', [
        'id' => $notif->id,
        'status' => 'Read',
    ]);
});

test('admin can delete a notification via AJAX and it triggers Notification Deleted log', function () {
    $notif = Notification::create([
        'title' => 'Trash Alert',
        'description' => 'Delete me',
        'priority' => 'Info',
        'category' => 'General',
        'status' => 'Read',
    ]);

    $response = $this->withSession($this->adminSession)
        ->delete("/notifications/{$notif->id}", [], ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('notifications', [
        'id' => $notif->id,
    ]);

    // Verify system triggered "Notification Deleted" event log
    $this->assertDatabaseHas('notifications', [
        'title' => 'Notification Deleted',
        'category' => 'System',
    ]);
});

test('admin can mark all as read and clear all notifications via AJAX', function () {
    Notification::create([
        'title' => 'Unread 1',
        'description' => 'Unread 1 description',
        'priority' => 'Info',
        'category' => 'General',
        'status' => 'Unread',
    ]);

    // Mark all as read
    $response = $this->withSession($this->adminSession)
        ->post('/notifications/read-all', [], ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertEquals(0, Notification::where('status', 'Unread')->count());

    // Clear all
    $response = $this->withSession($this->adminSession)
        ->delete('/notifications/clear', [], ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(200);
    $this->assertEquals(0, Notification::count());
});

test('system trigger endpoints log backup and new semester events', function () {
    // Backup Completed
    $response = $this->withSession($this->adminSession)
        ->post('/admin/backup', [], ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('notifications', [
        'title' => 'Backup Completed',
        'category' => 'System',
    ]);

    // New Semester Started
    $response = $this->withSession($this->adminSession)
        ->post('/admin/new-semester', [], ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('notifications', [
        'title' => 'New Semester Started',
        'category' => 'Semester',
    ]);
});

test('automatic triggers fire when entities are added', function () {
    // 1. Department Added trigger
    $this->withSession($this->adminSession)
        ->post('/admin/departments', [
            'name' => 'Mechanical Engineering',
            'code' => 'ME',
            'hod_name' => 'Dr. Paul',
        ])->assertRedirect('/admin/departments');

    $this->assertDatabaseHas('notifications', [
        'title' => 'Department Added',
        'category' => 'Department',
    ]);

    // Get the department ID for faculty creation
    $deptId = Department::where('name', 'Mechanical Engineering')->first()->id;

    // 2. Faculty Added trigger
    $this->withSession($this->adminSession)
        ->post('/admin/faculties', [
            'name' => 'Prof. James',
            'designation' => 'Assistant Professor',
            'email' => 'james@faculty.local',
            'password' => 'secret123',
            'department_id' => $deptId,
            'subjects' => 'Thermodynamics',
        ])->assertRedirect('/admin/faculties');

    $this->assertDatabaseHas('notifications', [
        'title' => 'Faculty Added',
        'category' => 'Faculty',
    ]);

    // 3. Student Registered trigger
    $this->post('/student/register', [
        'enrollment_number' => 'ENR102',
        'name' => 'Varun Kumar',
        'email' => 'varun@student.local',
        'department' => 'Mechanical Engineering',
        'semester' => '1',
        'student_class' => 'A',
        'divcon' => '1',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertRedirect('/');

    $this->assertDatabaseHas('notifications', [
        'title' => 'Student Registered',
        'category' => 'Student',
    ]);

    // 4. Faculty Workload Exceeded trigger
    $this->withSession($this->adminSession)
        ->post('/admin/faculty-workload', [
            'faculty_name' => 'Prof. James',
            'faculty_id' => 'FAC009',
            'department' => 'Mechanical Engineering',
            'subjects_assigned' => 'Thermodynamics, Heat Transfer',
            'theory_hours' => 12,
            'practical_hours' => 10,
            'assigned_classes' => 'ME-1, ME-2',
            'free_periods' => 'Monday 4th, Friday 2nd',
        ])->assertRedirect('/admin/faculty-workload');

    $this->assertDatabaseHas('notifications', [
        'title' => 'Faculty Workload Exceeded',
        'priority' => 'High',
        'category' => 'Workload',
    ]);
});
