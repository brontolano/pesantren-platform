<?php

namespace Modules\Payroll\Filament\Resources\Pages;

use Modules\Payroll\Filament\Resources\SlipGajiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlipGaji extends ListRecords
{
    protected static string $resource = SlipGajiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat Slip')];
    }
}
