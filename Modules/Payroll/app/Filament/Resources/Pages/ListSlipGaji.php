<?php

namespace Modules\Payroll\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Filament\Resources\SlipGajiResource;

class ListSlipGaji extends ListRecords
{
    protected static string $resource = SlipGajiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat Slip')];
    }
}
