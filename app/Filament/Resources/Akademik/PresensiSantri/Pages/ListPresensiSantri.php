<?php

namespace App\Filament\Resources\Akademik\PresensiSantri\Pages;

use App\Filament\Resources\Akademik\PresensiSantri\PresensiSantriResource;
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
