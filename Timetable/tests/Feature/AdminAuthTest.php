<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('admin authentication', function () {
    it('allows the admin to login and reach the dashboard', function () {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'admin',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertSessionHas('admin.auth', [
            'email' => 'admin@example.com',
            'name' => 'Admin User',
        ]);
    });

    it('rejects invalid admin credentials', function () {
        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrong',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    });
});
