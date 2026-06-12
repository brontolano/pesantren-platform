<?php

namespace App\Filament\Resources\Payroll\Pinjaman\Pages;

use App\Filament\Resources\Payroll\Pinjaman\PinjamanPegawaiResource;
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
