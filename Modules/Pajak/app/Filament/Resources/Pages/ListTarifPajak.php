<?php

namespace Modules\Pajak\Filament\Resources\Pages;

use Modules\Pajak\Filament\Resources\TarifPajakResource;
use Filament\Resources\Pages\ListRecords;

class ListTarifPajak extends ListRecords
{
    protected static string $resource = TarifPajakResource::class;
}
