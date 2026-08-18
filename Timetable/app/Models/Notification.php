<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'priority',
        'category',
        'status',
        'module_name',
        'reference_id',
        'created_by',

        // Backward compatibility attributes
        'type',
        'message',
        'audience',
    ];

    /**
     * The model's boot method.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Automatically sync new fields with old fields for backward compatibility on save
        static::saving(function (Notification $notification): void {
            if (empty($notification->message) && ! empty($notification->description)) {
                $notification->message = $notification->description;
            }
            if (empty($notification->description) && ! empty($notification->message)) {
                $notification->description = $notification->message;
            }
            if (empty($notification->type) && ! empty($notification->category)) {
                $notification->type = $notification->category;
            }
            if (empty($notification->category) && ! empty($notification->type)) {
                $notification->category = $notification->type;
            }
        });
    }

    /**
     * Get the description attribute, falling back to message if null.
     */
    public function getDescriptionAttribute(?string $value): ?string
    {
        return $value ?? $this->attributes['message'] ?? null;
    }

    /**
     * Get the category attribute, falling back to type if null.
     */
    public function getCategoryAttribute(?string $value): ?string
    {
        return $value ?? $this->attributes['type'] ?? null;
    }

    /**
     * Get the message attribute, falling back to description if null.
     */
    public function getMessageAttribute(?string $value): ?string
    {
        return $value ?? $this->attributes['description'] ?? null;
    }

    /**
     * Get the type attribute, falling back to category if null.
     */
    public function getTypeAttribute(?string $value): ?string
    {
        return $value ?? $this->attributes['category'] ?? null;
    }

    /**
     * Automatically trigger a system notification.
     *
     * @param  array<string, mixed>  $data
     */
    public static function trigger(string $event, array $data = []): self
    {
        $title = $event;
        $description = '';
        $priority = 'Info';
        $category = 'System';
        $moduleName = 'System';
        $audience = $data['audience'] ?? 'all';

        switch ($event) {
            case 'Timetable Generated':
                $title = 'Timetable Generated';
                $deptName = $data['department_name'] ?? 'All Departments';
                $semester = $data['semester'] ?? 'All Semesters';
                $academicYear = $data['academic_year'] ?? 'Current Year';
                $description = "Timetable successfully generated for {$deptName} (Semester {$semester}) for the Academic Year {$academicYear}.";
                $priority = 'Medium';
                $category = 'Timetable';
                $moduleName = 'Timetable';
                break;

            case 'Classroom Allocation Completed':
                $title = 'Classroom Allocation Completed';
                $count = $data['count'] ?? 0;
                $description = "Auto-allocation of classroom resources completed. {$count} classroom(s) successfully allocated.";
                $priority = 'Info';
                $category = 'Allocation';
                $moduleName = 'Classrooms';
                break;

            case 'Lab Allocation Completed':
                $title = 'Lab Allocation Completed';
                $count = $data['count'] ?? 0;
                $description = "Auto-allocation of laboratory resources completed. {$count} lab(s) successfully allocated.";
                $priority = 'Info';
                $category = 'Allocation';
                $moduleName = 'Classrooms';
                break;

            case 'Faculty Assigned':
                $title = 'Faculty Assigned';
                $faculty = $data['faculty_name'] ?? 'Faculty Member';
                $subject = $data['subject_name'] ?? 'Subject';
                $description = "Faculty {$faculty} has been successfully assigned to teach {$subject}.";
                $priority = 'Info';
                $category = 'Faculty';
                $moduleName = 'Faculty';
                break;

            case 'Faculty Workload Exceeded':
                $title = 'Faculty Workload Exceeded';
                $faculty = $data['faculty_name'] ?? 'Faculty Member';
                $hours = $data['total_hours'] ?? 0;
                $description = "Warning: Faculty member {$faculty} teaching workload of {$hours} hours exceeds the normal threshold of 18 hours.";
                $priority = 'High';
                $category = 'Workload';
                $moduleName = 'Faculty';
                break;

            case 'Conflict Detected':
                $title = 'Conflict Detected';
                $type = $data['type'] ?? 'Scheduling';
                $details = $data['details'] ?? 'Multiple bookings in same slot.';
                $description = "Alert: Timetable conflict detected: {$type} - {$details}.";
                $priority = 'High';
                $category = 'Conflict';
                $moduleName = 'Conflicts';
                break;

            case 'Subject Not Assigned':
                $title = 'Subject Not Assigned';
                $subject = $data['subject_name'] ?? 'Subject';
                $description = "Alert: Subject {$subject} remains unallocated due to room or schedule constraints.";
                $priority = 'High';
                $category = 'Allocation';
                $moduleName = 'Classrooms';
                break;

            case 'Classroom Capacity Exceeded':
                $title = 'Classroom Capacity Exceeded';
                $room = $data['room_number'] ?? 'Room';
                $capacity = $data['capacity'] ?? 0;
                $students = $data['student_count'] ?? 0;
                $description = "Classroom capacity exceeded for Room {$room} (Room Capacity: {$capacity}, Enrolled Students: {$students}).";
                $priority = 'High';
                $category = 'Capacity';
                $moduleName = 'Classrooms';
                break;

            case 'Student Added':
                $title = 'Student Registered';
                $name = $data['name'] ?? 'Student';
                $description = "New student {$name} has registered successfully on the portal.";
                $priority = 'Info';
                $category = 'Student';
                $moduleName = 'Students';
                $audience = 'all';
                break;

            case 'Faculty Added':
                $title = 'Faculty Added';
                $name = $data['name'] ?? 'Faculty';
                $description = "New faculty member {$name} has been added successfully to the system database.";
                $priority = 'Info';
                $category = 'Faculty';
                $moduleName = 'Faculty';
                $audience = 'all';
                break;

            case 'Department Added':
                $title = 'Department Added';
                $name = $data['name'] ?? 'Department';
                $description = "New department '{$name}' created successfully.";
                $priority = 'Info';
                $category = 'Department';
                $moduleName = 'Departments';
                break;

            case 'Academic Year Changed':
                $title = 'Academic Year Changed';
                $year = $data['academic_year'] ?? 'N/A';
                $description = "System academic year has been updated to {$year}. All active schedules shifted.";
                $priority = 'Info';
                $category = 'Academic Year';
                $moduleName = 'Settings';
                break;

            case 'Backup Completed':
                $title = 'Backup Completed';
                $description = 'System database backup completed successfully. Storage state secured.';
                $priority = 'Info';
                $category = 'System';
                $moduleName = 'System';
                break;

            case 'Notification Deleted':
                $title = 'Notification Deleted';
                $id = $data['id'] ?? 'N/A';
                $description = "Notification with ID {$id} has been permanently deleted from the database.";
                $priority = 'Info';
                $category = 'System';
                $moduleName = 'Notifications';
                break;

            case 'New Semester Started':
                $title = 'New Semester Started';
                $description = 'New academic semester started. Current timetables archived and database initialized.';
                $priority = 'Medium';
                $category = 'Semester';
                $moduleName = 'Settings';
                break;
        }

        return static::create([
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'category' => $category,
            'status' => 'Unread',
            'module_name' => $moduleName,
            'reference_id' => $data['reference_id'] ?? null,
            'created_by' => session('admin.auth.name') ?? 'System',

            // Backward compatibility syncing
            'type' => $category,
            'message' => $description,
            'audience' => $audience,
        ]);
    }
}
