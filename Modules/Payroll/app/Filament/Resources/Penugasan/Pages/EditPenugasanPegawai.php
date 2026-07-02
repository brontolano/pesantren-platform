<?php

namespace Modules\Payroll\Filament\Resources\Penugasan\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Payroll\Filament\Resources\Penugasan\PenugasanPegawaiResource;

class EditPenugasanPegawai extends EditRecord
{
    protected static string $resource = PenugasanPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
