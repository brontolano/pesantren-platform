<?php

namespace App\Filament\Resources\Pajak\Pages;

use App\Filament\Resources\Pajak\TarifPajakResource;
use Filament\Resources\Pages\ListRecords;

class ListTarifPajak extends ListRecords
{
    protected static string $resource = TarifPajakResource::class;
}
