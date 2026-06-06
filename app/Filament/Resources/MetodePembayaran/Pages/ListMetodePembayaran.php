<?php

namespace App\Filament\Resources\MetodePembayaran\Pages;

use App\Filament\Resources\MetodePembayaran\MetodePembayaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
