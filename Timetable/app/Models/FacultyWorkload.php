<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'faculty_name',
    'faculty_id',
    'department',
    'department_id',
    'semester',
    'subject_name',
    'subject_code',
    'subjects_assigned',
    'theory_hours',
    'practical_hours',
    'lecture_hours',
    'tutorial_hours',
    'lab_hours',
    'weekly_hours',
    'weekly_workload',
    'total_hours',
    'assigned_classes',
    'free_periods',
    'workload_status',
])]
class FacultyWorkload extends Model
{
    protected $appends = ['total_hours', 'workload_status'];

    public function getTotalHoursAttribute(): int
    {
        if (! empty($this->attributes['weekly_workload']) || (string) ($this->attributes['weekly_workload'] ?? '') === '0') {
            return (int) $this->attributes['weekly_workload'];
        }

        if (! empty($this->attributes['total_hours']) || (string) ($this->attributes['total_hours'] ?? '') === '0') {
            return (int) $this->attributes['total_hours'];
        }

        return (int) ($this->weekly_hours ?? 0) + (int) ($this->theory_hours ?? 0) + (int) ($this->practical_hours ?? 0);
    }

    public function getWorkloadStatusAttribute(): string
    {
        $status = $this->attributes['workload_status'] ?? null;

        if (! empty($status)) {
            return (string) $status;
        }

        return self::calculateStatus($this->getTotalHoursAttribute());
    }

    public function getStatusAttribute(): string
    {
        return $this->getWorkloadStatusAttribute();
    }

    public static function calculateStatus(int $totalHours): string
    {
        $normalThreshold = (int) config('faculty_workload.normal_threshold', 18);

        return $totalHours > $normalThreshold ? 'Overloaded' : 'Normal';
    }
}
