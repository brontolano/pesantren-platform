<?php
namespace Modules\Pengadaan\Filament\Resources\Supplier\Pages;
use Modules\Pengadaan\Filament\Resources\Supplier\SupplierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
