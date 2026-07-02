<?php

namespace Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasi\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasiResource;

class ListInventoryLokasi extends ListRecords
{
    protected static string $resource = InventoryLokasiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Lokasi')];
    }
}
