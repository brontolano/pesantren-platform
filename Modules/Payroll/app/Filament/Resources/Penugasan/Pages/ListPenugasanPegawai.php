<?php

namespace Modules\Payroll\Filament\Resources\Penugasan\Pages;

use Modules\Payroll\Filament\Resources\Penugasan\PenugasanPegawaiResource;
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
