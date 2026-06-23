<?php

namespace Modules\PPDB\Filament\Resources\JadwalUjian\Pages;

use Modules\PPDB\Filament\Resources\JadwalUjianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
