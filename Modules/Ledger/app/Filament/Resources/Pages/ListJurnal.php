<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Filament\Resources\JurnalResource;

class ListJurnal extends ListRecords
{
    protected static string $resource = JurnalResource::class;
}
