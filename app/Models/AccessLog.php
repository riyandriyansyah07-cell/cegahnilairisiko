<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'lihat_hasil' => 'Membuka hasil risiko',
            'daftar_bimbingan' => 'Melihat di daftar bimbingan',
            'detail_faktor' => 'Membuka faktor utama',
            default => $this->action,
        };
    }

    /** Kode samar yang aman ditampilkan ke admin. */
    public static function shortRef(string $ref): string
    {
        return 'MHS-'.strtoupper(substr($ref, 0, 8));
    }
}
