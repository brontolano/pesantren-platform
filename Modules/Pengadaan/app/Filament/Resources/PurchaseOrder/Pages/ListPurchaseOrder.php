<?php

namespace Modules\Pengadaan\Filament\Resources\PurchaseOrder\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\PurchaseOrderResource;

class ListPurchaseOrder extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat PO')];
    }
}
