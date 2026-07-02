<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Filament\Resources\DivisiUnitResource;

class ListDivisiUnit extends ListRecords
{
    protected static string $resource = DivisiUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
