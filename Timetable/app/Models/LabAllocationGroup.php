<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'department_id',
    'semester',
    'class_name',
    'group_name',
    'divisions',
    'classroom_id',
])]
class LabAllocationGroup extends Model
{
    protected function casts(): array
    {
        return [
            'divisions' => 'array',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
