<?php

namespace App\Filament\Resources\Payroll\Penugasan\Pages;

use App\Filament\Resources\Payroll\Penugasan\PenugasanPegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenugasanPegawai extends ListRecords
{
    protected static string $resource = PenugasanPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Penugasan')];
    }
}
