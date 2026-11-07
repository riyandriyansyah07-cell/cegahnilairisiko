<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricalDataset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['label_counts' => 'array'];
    }

    public function records(): HasMany
    {
        return $this->hasMany(HistoricalRecord::class);
    }
}
