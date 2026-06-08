<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\PeriodeAkuntansiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPeriodeAkuntansi extends ListRecords
{
    protected static string $resource = PeriodeAkuntansiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
