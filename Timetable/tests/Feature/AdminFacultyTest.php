<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFacultyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_faculty_and_stores_their_record_in_a_department_folder(): void
    {
        Storage::disk('local')->deleteDirectory('faculty-records');

        $department = Department::create([
            'name' => 'Computer Science',
            'code' => 'CS',
            'description' => 'Department for computing courses',
        ]);

        $response = $this->withSession([
            'admin.auth' => [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
            ],
        ])->post('/admin/faculties', [
            'name' => 'Dr. Maya Patel',
            'mobile_number' => '9876543210',
            'email' => 'maya@example.com',
            'qualification' => 'PhD in Computer Science',
            'department_id' => $department->id,
            'subjects' => 'Algorithms, Database Systems',
        ]);

        $response->assertRedirect('/admin/faculties');

        $this->assertDatabaseHas('faculties', [
            'name' => 'Dr. Maya Patel',
            'email' => 'maya@example.com',
            'department_id' => $department->id,
        ]);

        $faculty = Faculty::where('email', 'maya@example.com')->first();
        $directory = 'faculty-records/'.Str::slug($department->name);
        $filePath = $directory.'/faculty-'.$faculty->id.'.json';

        $this->assertTrue(Storage::disk('local')->exists($filePath));
    }
}
