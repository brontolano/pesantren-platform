<?php

namespace Modules\PPDB\Filament\Resources\PaketSoal\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\PPDB\Filament\Resources\PaketSoal\PaketSoalResource;

class ListPaketSoal extends ListRecords
{
    protected static string $resource = PaketSoalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
