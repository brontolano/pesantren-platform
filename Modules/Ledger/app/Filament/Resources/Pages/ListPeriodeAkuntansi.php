<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Filament\Resources\PeriodeAkuntansiResource;

class ListPeriodeAkuntansi extends ListRecords
{
    protected static string $resource = PeriodeAkuntansiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
