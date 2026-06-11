<?php

namespace App\Filament\Resources\Payroll\Kontrak\Pages;

use App\Filament\Resources\Payroll\Kontrak\KontrakKerjaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKontrakKerja extends ListRecords
{
    protected static string $resource = KontrakKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kontrak/SK')];
    }
}
