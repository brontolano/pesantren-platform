<?php

namespace Modules\Akademik\Filament\Resources\SesiUjianAkademik\Pages;

use Modules\Akademik\Filament\Resources\SesiUjianAkademik\SesiUjianAkademikResource;
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
