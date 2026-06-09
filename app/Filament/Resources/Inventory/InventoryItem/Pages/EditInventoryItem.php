<?php

namespace App\Filament\Resources\Inventory\InventoryItem\Pages;

use App\Filament\Resources\Inventory\InventoryItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
