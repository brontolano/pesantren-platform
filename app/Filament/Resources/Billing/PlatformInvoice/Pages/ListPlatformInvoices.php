<?php

namespace App\Filament\Resources\Billing\PlatformInvoice\Pages;

use App\Filament\Resources\Billing\PlatformInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformInvoices extends ListRecords
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
