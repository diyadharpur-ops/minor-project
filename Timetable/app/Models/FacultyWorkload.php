<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'faculty_name',
    'faculty_id',
    'department',
    'subjects_assigned',
    'theory_hours',
    'practical_hours',
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
        if (! empty($this->attributes['total_hours']) || $this->attributes['total_hours'] === '0') {
            return (int) $this->attributes['total_hours'];
        }

        return (int) ($this->theory_hours ?? 0) + (int) ($this->practical_hours ?? 0);
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
