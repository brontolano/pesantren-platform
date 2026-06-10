<?php

namespace App\Filament\Resources\PPDB\PaketSoal\Pages;

use App\Filament\Resources\PPDB\PaketSoal\PaketSoalResource;
use Filament\Resources\Pages\ListRecords;

class ListPaketSoal extends ListRecords
{
    protected static string $resource = PaketSoalResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
