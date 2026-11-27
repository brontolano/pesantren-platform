<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment('Buat sistem pesantren yang sederhana untuk digunakan.');
})->purpose('Tampilkan kutipan inspiratif');
