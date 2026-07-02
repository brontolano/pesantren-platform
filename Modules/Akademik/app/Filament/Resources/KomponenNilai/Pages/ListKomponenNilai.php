<?php

namespace Modules\Akademik\Filament\Resources\KomponenNilai\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\KomponenNilai\KomponenNilaiResource;

class ListKomponenNilai extends ListRecords
{
    protected static string $resource = KomponenNilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
