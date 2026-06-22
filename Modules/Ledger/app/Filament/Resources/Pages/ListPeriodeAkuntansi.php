<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Modules\Ledger\Filament\Resources\PeriodeAkuntansiResource;
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
