<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'faculty_id',
    'department_id',
    'subject_id',
    'subject_type',
    'semester',
    'class_name',
    'division',
    'theory_hours',
    'practical_hours',
    'assigned_classes',
    'free_periods',
    'timetable_id',
])]
class FacultyWorkload extends Model
{
    protected $appends = ['total_hours', 'status'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function timetableEntry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class, 'timetable_id');
    }

    public function getTotalHoursAttribute(): int
    {
        return (int) ($this->theory_hours ?? 0) + (int) ($this->practical_hours ?? 0);
    }

    public function getStatusAttribute(): string
    {
        return self::statusForHours($this->getTotalHoursAttribute());
    }

    public static function statusForHours(int $totalHours): string
    {
        $normalThreshold = (int) config('faculty_workload.normal_threshold', 18);
        $highThreshold = (int) config('faculty_workload.high_threshold', 24);

        if ($totalHours <= $normalThreshold) {
            return 'Normal';
        }

        if ($totalHours <= $highThreshold) {
            return 'High';
        }

        return 'Overloaded';
    }
}
