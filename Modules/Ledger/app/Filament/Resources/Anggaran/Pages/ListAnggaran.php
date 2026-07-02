<?php

namespace Modules\Ledger\Filament\Resources\Anggaran\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Filament\Resources\AnggaranResource;

class ListAnggaran extends ListRecords
{
    protected static string $resource = AnggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
