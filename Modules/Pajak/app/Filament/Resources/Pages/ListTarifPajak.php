<?php

namespace Modules\Pajak\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Pajak\Filament\Resources\TarifPajakResource;

class ListTarifPajak extends ListRecords
{
    protected static string $resource = TarifPajakResource::class;
}
