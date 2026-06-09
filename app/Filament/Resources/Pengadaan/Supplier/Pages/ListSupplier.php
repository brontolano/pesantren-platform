<?php
namespace App\Filament\Resources\Pengadaan\Supplier\Pages;
use App\Filament\Resources\Pengadaan\Supplier\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListSupplier extends ListRecords
{
    protected static string $resource = SupplierResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Tambah Supplier')]; }
}
