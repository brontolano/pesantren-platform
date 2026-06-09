<?php

namespace App\Filament\Resources\Ledger\Anggaran\Pages;

use App\Filament\Resources\Ledger\AnggaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnggaran extends ListRecords
{
    protected static string $resource = AnggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
