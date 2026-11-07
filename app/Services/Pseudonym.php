<?php

namespace App\Services;

use RuntimeException;

/** Penyamaran NIM sebelum data akademik disimpan (HMAC-SHA256 dengan garam). */
class Pseudonym
{
    public static function ref(string $nim): string
    {
        $salt = trim((string) config('risk.pseudonym_salt'));
        if ($salt === '') {
            throw new RuntimeException('PSEUDONYM_SALT wajib diisi di .env agar penyamaran NIM tidak berubah saat APP_KEY diganti.');
        }

        return hash_hmac('sha256', trim($nim), $salt);
    }
}
