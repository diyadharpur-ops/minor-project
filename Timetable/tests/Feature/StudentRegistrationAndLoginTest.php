<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentRegistrationAndLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registration_creates_real_user_and_shows_success_message(): void
    {
        $response = $this->from('/')->post('/student/register', [
            'enrollment_number' => '2026CE001',
            'name' => 'Rahul Patel',
            'email' => 'rahul@example.com',
            'department' => 'Computer Engineering',
            'semester' => '5',
            'student_class' => 'CE-A',
            'divcon' => '1',
            'password' => 'Student@123',
            'password_confirmation' => 'Student@123',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('student_register_success');

        $this->assertDatabaseHas('users', [
            'enrollment_number' => '2026CE001',
            'name' => 'Rahul Patel',
            'email' => 'rahul@example.com',
        ]);

        $user = User::where('enrollment_number', '2026CE001')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Student@123', $user->password));
    }

    public function test_student_login_authenticates_against_enrollment_number_and_password(): void
    {
        User::create([
            'name' => 'Rahul Patel',
            'enrollment_number' => '2026CE001',
            'email' => 'rahul@example.com',
            'department' => 'Computer Engineering',
            'semester' => '5',
            'student_class' => 'CE-A',
            'divcon' => '1',
            'password' => Hash::make('Student@123'),
        ]);

        $response = $this->from('/')->post('/student/login', [
            'enrollment_number' => '2026CE001',
            'password' => 'Student@123',
        ]);

        $response->assertRedirect('/student/dashboard');
        $response->assertSessionHas('student.auth.id');
        $this->assertSame('2026CE001', session('student.auth.enrollment_number'));
    }

    public function test_student_login_shows_specific_error_messages_for_missing_and_invalid_credentials(): void
    {
        User::create([
            'name' => 'Rahul Patel',
            'enrollment_number' => '2026CE001',
            'email' => 'rahul@example.com',
            'department' => 'Computer Engineering',
            'semester' => '5',
            'student_class' => 'CE-A',
            'divcon' => '1',
            'password' => Hash::make('Student@123'),
        ]);

        $this->from('/')->post('/student/login', [
            'enrollment_number' => '2026CE999',
            'password' => 'Student@123',
        ])->assertSessionHasErrors(['enrollment_number' => 'Student account not found.']);

        $this->from('/')->post('/student/login', [
            'enrollment_number' => '2026CE001',
            'password' => 'WrongPassword',
        ])->assertSessionHasErrors(['enrollment_number' => 'Invalid enrollment number or password.']);
    }

    public function test_admin_can_view_registered_students_in_manage_students_list(): void
    {
        User::create([
            'name' => 'Rahul Patel',
            'enrollment_number' => '2026CE001',
            'email' => 'rahul@example.com',
            'department' => 'Computer Engineering',
            'semester' => '5',
            'student_class' => 'CE-A',
            'divcon' => '1',
            'password' => Hash::make('Student@123'),
        ]);

        $this->withSession([
            'admin.auth' => [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
            ],
        ])->get('/admin/students')
            ->assertOk()
            ->assertSee('2026CE001')
            ->assertSee('Rahul Patel')
            ->assertSee('Computer Engineering');
    }
}
