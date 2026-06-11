<?php

namespace App\Filament\Resources\Kesehatan\Obat\Pages;

use App\Filament\Resources\Kesehatan\ObatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListObat extends ListRecords
{
    protected static string $resource = ObatResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
