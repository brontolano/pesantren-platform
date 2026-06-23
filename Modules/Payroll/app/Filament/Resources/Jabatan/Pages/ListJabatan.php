<?php

namespace Modules\Payroll\Filament\Resources\Jabatan\Pages;

use Modules\Payroll\Filament\Resources\Jabatan\JabatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJabatan extends ListRecords
{
    protected static string $resource = JabatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Jabatan')];
    }
}
