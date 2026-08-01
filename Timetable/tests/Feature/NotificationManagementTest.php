<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_notification_and_students_can_view_it(): void
    {
        $user = User::create([
            'name' => 'Asha Student',
            'enrollment_number' => 'ENR001',
            'email' => 'ENR001@student.local',
            'department' => 'Computer Science',
            'semester' => '3',
            'student_class' => 'A',
            'divcon' => '1',
            'password' => bcrypt('secret123'),
        ]);

        $this->withSession([
            'admin.auth' => [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
            ],
        ])->post('/admin/notifications', [
            'title' => 'Holiday Notice',
            'type' => 'Holiday',
            'message' => 'Campus will remain closed on Monday.',
            'audience' => 'student',
        ])->assertRedirect('/admin/notifications');

        $this->assertDatabaseHas('notifications', [
            'title' => 'Holiday Notice',
            'type' => 'Holiday',
            'audience' => 'student',
        ]);

        $this->withSession([
            'student.auth' => [
                'id' => $user->id,
                'name' => $user->name,
                'enrollment_number' => $user->enrollment_number,
                'department' => $user->department,
                'semester' => $user->semester,
                'student_class' => $user->student_class,
                'divcon' => $user->divcon,
            ],
        ])->get('/student/notifications')
            ->assertSee('Holiday Notice')
            ->assertSee('Campus will remain closed on Monday.');
    }
}
