<?php

namespace Modules\Akademik\Filament\Resources\PresensiSantri\Pages;

use Modules\Akademik\Filament\Resources\PresensiSantri\PresensiSantriResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPresensiSantri extends ListRecords
{
    protected static string $resource = PresensiSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Catat Presensi')];
    }
}
