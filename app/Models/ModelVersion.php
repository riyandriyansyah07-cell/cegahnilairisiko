<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'metrics' => 'array',
            'macro_f1' => 'float',
            'recall_high' => 'float',
            'meets_target' => 'boolean',
            'is_active' => 'boolean',
            'trained_at' => 'datetime',
        ];
    }
}
