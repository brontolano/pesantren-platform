<?php

namespace App\Filament\Resources\PPDB\SoalUjian\Pages;

use App\Filament\Resources\PPDB\SoalUjianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSoalUjian extends ListRecords
{
    protected static string $resource = SoalUjianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
