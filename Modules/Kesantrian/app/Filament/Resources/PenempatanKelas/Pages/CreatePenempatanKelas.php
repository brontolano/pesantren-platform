<?php

namespace Modules\Kesantrian\Filament\Resources\PenempatanKelas\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Kesantrian\Filament\Resources\PenempatanKelas\PenempatanKelasResource;

class CreatePenempatanKelas extends CreateRecord
{
    protected static string $resource = PenempatanKelasResource::class;

    // Sinkronisasi is_current lama + Santri.kelas_id kini SATU jalur:
    // hook booted() di Modules\Kesantrian\Models\PenempatanKelas (WP-05).
}
