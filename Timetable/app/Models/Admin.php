<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Admin extends Model
{
    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'remember_token',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password_changed_at' => 'datetime',
        ];
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
