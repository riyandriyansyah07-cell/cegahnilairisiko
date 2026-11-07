<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'changed_at' => 'datetime'];
    }
}
