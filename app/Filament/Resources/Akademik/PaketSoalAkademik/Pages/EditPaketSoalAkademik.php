<?php

namespace App\Filament\Resources\Akademik\PaketSoalAkademik\Pages;

use App\Filament\Resources\Akademik\PaketSoalAkademik\PaketSoalAkademikResource;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Services\UjianAkademikService;

class EditPaketSoalAkademik extends EditRecord
{
    protected static string $resource = PaketSoalAkademikResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(UjianAkademikService::class)->pastikanBolehKelolaPaket(
            auth()->user(),
            (int) ($data['pelajaran_id'] ?? $this->record->pelajaran_id),
            $this->record,
        );

        return $data;
    }
}
