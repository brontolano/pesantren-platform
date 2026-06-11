<?php

namespace App\Filament\Resources\Akademik\SesiUjianAkademik\Pages;

use App\Filament\Resources\Akademik\SesiUjianAkademik\SesiUjianAkademikResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Akademik\Services\UjianAkademikService;

class CreateSesiUjianAkademik extends CreateRecord
{
    protected static string $resource = SesiUjianAkademikResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(UjianAkademikService::class)
            ->pastikanBolehKelolaPaket(auth()->user(), (int) $data['pelajaran_id']);

        $data['created_by'] = auth()->id();

        return $data;
    }
}
