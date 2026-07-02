<?php

namespace Modules\Akademik\Filament\Resources\KomponenNilai\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Filament\Resources\KomponenNilai\KomponenNilaiResource;

class EditKomponenNilai extends EditRecord
{
    protected static string $resource = KomponenNilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
