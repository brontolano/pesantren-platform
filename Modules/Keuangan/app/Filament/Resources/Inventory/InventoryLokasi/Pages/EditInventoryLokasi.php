<?php

namespace Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasi\Pages;

use Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryLokasi extends EditRecord
{
    protected static string $resource = InventoryLokasiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
