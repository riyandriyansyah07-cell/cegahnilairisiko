<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'email' => 'Format :attribute tidak valid.',
    'unique' => ':attribute sudah dipakai.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'regex' => 'Format :attribute tidak valid.',
    'file' => ':attribute harus berupa berkas.',
    'max' => [
        'string' => ':attribute maksimal :max karakter.',
        'file' => 'Ukuran :attribute maksimal :max KB.',
        'numeric' => ':attribute maksimal :max.',
        'array' => ':attribute maksimal :max item.',
    ],
    'min' => [
        'string' => ':attribute minimal :min karakter.',
        'file' => 'Ukuran :attribute minimal :min KB.',
        'numeric' => ':attribute minimal :min.',
        'array' => ':attribute minimal :min item.',
    ],
    'attributes' => [
        'name' => 'Nama',
        'username' => 'NIM atau ID',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'role' => 'Peran',
        'dpa_id' => 'DPA',
        'file' => 'Berkas',
        'dataset_id' => 'Dataset',
        'granted' => 'Izin',
    ],
];
