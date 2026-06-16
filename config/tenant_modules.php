<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default aktivasi modul "released" saat provisioning tenant baru
    |--------------------------------------------------------------------------
    |
    | Default BARU (false): tenant baru lahir RAMPING — hanya modul INTI yang
    | aktif. Modul "released" (tambahan) dipasang sendiri oleh super admin
    | pesantren lewat Pusat Modul (self-installer) secara bertahap.
    |
    | Set true (env MODULES_DEFAULT_ACTIVE_RELEASED=true) untuk mengembalikan
    | perilaku lama "semua modul released aktif sejak awal" — jalur rollback.
    |
    */
    'default_active_released' => env('MODULES_DEFAULT_ACTIVE_RELEASED', false),
];
