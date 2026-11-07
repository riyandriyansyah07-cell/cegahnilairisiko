<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['grade_point' => 'float', 'attendance' => 'float'];
    }
}
