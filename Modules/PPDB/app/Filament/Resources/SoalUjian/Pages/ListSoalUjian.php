<?php

namespace Modules\PPDB\Filament\Resources\SoalUjian\Pages;

use Modules\PPDB\Filament\Resources\SoalUjianResource;
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
