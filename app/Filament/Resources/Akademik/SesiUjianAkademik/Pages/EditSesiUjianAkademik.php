<?php

namespace App\Filament\Resources\Akademik\SesiUjianAkademik\Pages;

use App\Filament\Resources\Akademik\SesiUjianAkademik\SesiUjianAkademikResource;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Services\UjianAkademikService;

class EditSesiUjianAkademik extends EditRecord
{
    protected static string $resource = SesiUjianAkademikResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(UjianAkademikService::class)->pastikanBolehKelolaPaket(
            auth()->user(),
            (int) ($data['pelajaran_id'] ?? $this->record->pelajaran_id),
        );

        return $data;
    }
}
