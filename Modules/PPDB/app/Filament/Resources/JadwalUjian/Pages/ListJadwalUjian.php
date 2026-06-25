<?php

namespace Modules\PPDB\Filament\Resources\JadwalUjian\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\PPDB\Filament\Resources\JadwalUjianResource;

class ListJadwalUjian extends ListRecords
{
    protected static string $resource = JadwalUjianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
