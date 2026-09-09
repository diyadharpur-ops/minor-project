<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'subject_code',
    'semester',
    'department_id',
    'lecture_credit',
    'lab_credit',
    'tutorial_credit',
    'folder_path',
])]
class Subject extends Model
{
    public function getWeeklyHoursAttribute(): int
    {
        return ((int) ($this->lecture_credit ?? 0))
            + ((int) ($this->lab_credit ?? 0) * 2)
            + ((int) ($this->tutorial_credit ?? 0));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }
}
