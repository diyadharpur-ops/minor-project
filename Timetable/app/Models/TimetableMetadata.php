<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'department_id',
    'semester',
    'division',
    'academic_year',
    'term',
    'term_code',
    'term_dates',
    'wef_date',
    'sbt_dates',
])]
class TimetableMetadata extends Model
{
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
