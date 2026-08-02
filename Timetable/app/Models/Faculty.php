<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'designation', 'password', 'department_id', 'subjects', 'folder_path'])]
class Faculty extends Model
{
    protected $hidden = ['password'];
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
    
    // Hash password when setting attribute
    public function setPasswordAttribute($value)
    {
        if (empty($value)) {
            return;
        }

        $this->attributes['password'] = Hash::make($value);
    }
}
