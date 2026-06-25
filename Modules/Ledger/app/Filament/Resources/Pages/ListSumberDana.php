<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Filament\Resources\SumberDanaResource;

class ListSumberDana extends ListRecords
{
    protected static string $resource = SumberDanaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
