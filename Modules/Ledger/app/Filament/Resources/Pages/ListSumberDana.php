<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Modules\Ledger\Filament\Resources\SumberDanaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSumberDana extends ListRecords
{
    protected static string $resource = SumberDanaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
