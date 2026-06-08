<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\JurnalResource;
use Filament\Resources\Pages\ListRecords;

class ListJurnal extends ListRecords
{
    protected static string $resource = JurnalResource::class;
}
