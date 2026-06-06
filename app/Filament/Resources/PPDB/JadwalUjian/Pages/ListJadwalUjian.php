<?php

namespace App\Filament\Resources\PPDB\JadwalUjian\Pages;

use App\Filament\Resources\PPDB\JadwalUjianResource;
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
