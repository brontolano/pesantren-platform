<?php

namespace Modules\Payroll\Filament\Resources\Pinjaman\Pages;

use Modules\Payroll\Filament\Resources\Pinjaman\PinjamanPegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPinjamanPegawai extends ListRecords
{
    protected static string $resource = PinjamanPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajukan Kasbon')];
    }
}
