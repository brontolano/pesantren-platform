<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Modules\Ledger\Filament\Resources\DivisiUnitResource;
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
