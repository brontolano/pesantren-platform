<?php

namespace Modules\Payroll\Filament\Resources\Kontrak\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Filament\Resources\Kontrak\KontrakKerjaResource;

class ListKontrakKerja extends ListRecords
{
    protected static string $resource = KontrakKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kontrak/SK')];
    }
}
