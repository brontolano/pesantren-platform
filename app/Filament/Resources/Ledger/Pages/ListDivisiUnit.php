<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\DivisiUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDivisiUnit extends ListRecords
{
    protected static string $resource = DivisiUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
