<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'designation', 'password', 'department_id', 'subjects', 'folder_path', 'password_changed_at'])]
class Faculty extends Model
{
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function setPasswordAttribute($value): void
    {
        if (is_null($value) || $value === '') {
            $this->attributes['password'] = null;

            return;
        }

        if (is_string($value) && preg_match('~^\$2[aby]\$\d{2}\$[./A-Za-z0-9]{53}$~', $value) === 1) {
            $this->attributes['password'] = $value;

            return;
        }

        $this->attributes['password'] = Hash::make($value);
    }
}
