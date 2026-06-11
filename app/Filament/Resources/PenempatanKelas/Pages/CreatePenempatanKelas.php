<?php

namespace App\Filament\Resources\PenempatanKelas\Pages;

use App\Filament\Resources\PenempatanKelas\PenempatanKelasResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePenempatanKelas extends CreateRecord
{
    protected static string $resource = PenempatanKelasResource::class;

    // Sinkronisasi is_current lama + Santri.kelas_id kini SATU jalur:
    // hook booted() di Modules\Kesantrian\Models\PenempatanKelas (WP-05).
}

