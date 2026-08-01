<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['room_number', 'room_capacity', 'room_type', 'availability'])]
class Classroom extends Model
{
    use HasFactory;
}
