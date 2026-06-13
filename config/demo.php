<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-login demo (TWA / showcase tanpa login)
    |--------------------------------------------------------------------------
    |
    | Bila true, rute publik /demo-masuk/{peran} akan otomatis melogin user
    | demo bawaan lalu mengarahkan ke PWA terkait — TANPA kredensial. Dipakai
    | untuk aplikasi TWA demo yang bisa di-install di smartphone.
    |
    | KEAMANAN: jalur ini melewati layar login. Controller MENGUNCI-nya ganda:
    |   (1) flag ini harus true, DAN
    |   (2) tenant aktif harus persis 'demo'.
    | Jangan pernah aktifkan untuk tenant produksi berisi data nyata.
    |
    */
    'autologin_enabled' => (bool) env('DEMO_AUTOLOGIN', false),

    // Tenant yang diizinkan memakai auto-login demo.
    'autologin_tenant' => env('DEMO_AUTOLOGIN_TENANT', 'demo'),
];
