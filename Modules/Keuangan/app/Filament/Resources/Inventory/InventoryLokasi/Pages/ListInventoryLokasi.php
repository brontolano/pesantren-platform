<?php

namespace Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasi\Pages;

use Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLokasi extends ListRecords
{
    protected static string $resource = InventoryLokasiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Lokasi')];
    }
}
