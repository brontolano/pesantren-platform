<?php

namespace App\Filament\Resources\Payroll\Jabatan\Pages;

use App\Filament\Resources\Payroll\Jabatan\JabatanResource;
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
