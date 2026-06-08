<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\AkunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAkun extends ListRecords
{
    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
