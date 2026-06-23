<?php

namespace Modules\PPDB\Filament\Resources\GelombangPpdb\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\PPDB\Filament\Resources\GelombangPpdbResource;

class ListGelombangPpdb extends ListRecords
{
    protected static string $resource = GelombangPpdbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
