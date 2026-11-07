<?php

return [
    // Garam HMAC untuk menyamarkan NIM. Wajib diisi di .env dan tidak memakai APP_KEY.
    'pseudonym_salt' => env('PSEUDONYM_SALT', ''),

    // "rule" memakai model aturan di PHP. "http" memakai layanan model Python (ml-service),
    // dan kembali ke model aturan bila layanan tidak menjawab.
    'engine' => env('RISK_ENGINE', 'rule'),
    'service_url' => rtrim((string) env('RISK_SERVICE_URL', 'http://127.0.0.1:8001'), '/'),
    'service_token' => env('RISK_SERVICE_TOKEN'),
    'service_timeout' => 3,   // detik, prediksi (NFR-04: halaman hasil maksimal 3 detik)
    'train_timeout' => 120,   // detik, pelatihan

    // Target awal NFR-02. Versi model hanya bisa diaktifkan bila keduanya terpenuhi.
    'target_macro_f1' => 0.70,
    'target_recall_high' => 0.80,

    'import_max_kb' => 2048,
];
