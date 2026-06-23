<?php

namespace Modules\PPDB\Filament\Resources\PaketSoal\Pages;

use Modules\PPDB\Filament\Resources\PaketSoal\PaketSoalResource;
use Filament\Resources\Pages\ListRecords;

class ListPaketSoal extends ListRecords
{
    protected static string $resource = PaketSoalResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
