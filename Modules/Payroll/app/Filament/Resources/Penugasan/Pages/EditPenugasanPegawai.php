<?php

namespace Modules\Payroll\Filament\Resources\Penugasan\Pages;

use Modules\Payroll\Filament\Resources\Penugasan\PenugasanPegawaiResource;
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
