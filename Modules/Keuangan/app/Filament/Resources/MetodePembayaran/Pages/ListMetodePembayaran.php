<?php

namespace Modules\Keuangan\Filament\Resources\MetodePembayaran\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\MetodePembayaran\MetodePembayaranResource;

class ListMetodePembayaran extends ListRecords
{
    protected static string $resource = MetodePembayaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
