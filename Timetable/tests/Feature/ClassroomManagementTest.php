<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create and list classrooms', function () {
    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classrooms')
        ->assertStatus(200);

    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->post('/admin/classrooms', [
        'room_number' => 'A-101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ])->assertRedirect('/admin/classrooms');

    $this->assertDatabaseHas('classrooms', [
        'room_number' => 'A-101',
        'room_capacity' => 40,
        'room_type' => 'Classroom',
        'availability' => 'Available',
    ]);
});

test('admin classroom allocation page loads without demo data', function () {
    $this->withSession([
        'admin.auth' => [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ],
    ])->get('/admin/classroom-allocation')
        ->assertOk()
        ->assertSee('Classroom & Lab Allocation')
        ->assertSee('No rooms added yet.')
        ->assertSee('No allocations found.');
});
