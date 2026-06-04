<?php

namespace App\Filament\Resources\PenempatanKelas\Pages;

use App\Filament\Resources\PenempatanKelas\PenempatanKelasResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;

class CreatePenempatanKelas extends CreateRecord
{
    protected static string $resource = PenempatanKelasResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Nonaktifkan penempatan lama
        PenempatanKelas::where('santri_id', $record->santri_id)
            ->where('id', '!=', $record->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        // Update kelas_id di santri
        Santri::where('id', $record->santri_id)
            ->update(['kelas_id' => $record->kelas_id]);
    }
}

