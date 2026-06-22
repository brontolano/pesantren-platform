<?php

namespace Modules\Keuangan\Filament\Resources\Inventory\InventoryItem\Pages;

use Modules\Keuangan\Filament\Resources\Inventory\InventoryItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItem extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
