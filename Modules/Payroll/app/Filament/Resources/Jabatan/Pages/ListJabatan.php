<?php

namespace Modules\Payroll\Filament\Resources\Jabatan\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Filament\Resources\Jabatan\JabatanResource;

class ListJabatan extends ListRecords
{
    protected static string $resource = JabatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Jabatan')];
    }
}
