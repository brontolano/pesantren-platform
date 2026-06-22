<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Modules\Ledger\Filament\Resources\JurnalResource;
use Filament\Resources\Pages\ListRecords;

class ListJurnal extends ListRecords
{
    protected static string $resource = JurnalResource::class;
}
