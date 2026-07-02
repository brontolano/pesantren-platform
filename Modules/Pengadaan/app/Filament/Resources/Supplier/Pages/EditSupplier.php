<?php

namespace Modules\Pengadaan\Filament\Resources\Supplier\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Pengadaan\Filament\Resources\Supplier\SupplierResource;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
