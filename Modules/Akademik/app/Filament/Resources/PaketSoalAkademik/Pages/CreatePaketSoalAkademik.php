<?php

namespace Modules\Akademik\Filament\Resources\PaketSoalAkademik\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Akademik\Filament\Resources\PaketSoalAkademik\PaketSoalAkademikResource;
use Modules\Akademik\Services\UjianAkademikService;

class CreatePaketSoalAkademik extends CreateRecord
{
    protected static string $resource = PaketSoalAkademikResource::class;

    /** Enforce pengampu per-pelajaran + kepemilikan di SATU tempat (service). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(UjianAkademikService::class)
            ->pastikanBolehKelolaPaket(auth()->user(), (int) $data['pelajaran_id']);

        $data['created_by'] = auth()->id();
        $data['kategori_ujian'] = 'Akademik';

        return $data;
    }
}
