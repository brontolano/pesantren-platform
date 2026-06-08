<?php

namespace App\Filament\Resources\Payroll\Pages;

use App\Filament\Resources\Payroll\SlipGajiResource;
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
