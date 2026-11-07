<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'features' => 'array',
            'factors' => 'array',
            'suggestions' => 'array',
            'data_as_of' => 'date',
        ];
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }
}
