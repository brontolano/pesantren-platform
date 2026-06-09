<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\SumberDanaResource;
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
