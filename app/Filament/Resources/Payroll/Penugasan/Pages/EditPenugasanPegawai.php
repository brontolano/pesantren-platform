<?php

namespace App\Filament\Resources\Payroll\Penugasan\Pages;

use App\Filament\Resources\Payroll\Penugasan\PenugasanPegawaiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPenugasanPegawai extends EditRecord
{
    protected static string $resource = PenugasanPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
