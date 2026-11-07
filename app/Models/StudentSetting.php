<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['remind_in_app' => 'boolean', 'remind_email' => 'boolean'];
    }
}
