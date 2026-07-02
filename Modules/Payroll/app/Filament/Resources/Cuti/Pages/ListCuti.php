<?php

namespace Modules\Payroll\Filament\Resources\Cuti\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Filament\Resources\Cuti\CutiResource;

class ListCuti extends ListRecords
{
    protected static string $resource = CutiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajukan Cuti')];
    }
}
