<?php

namespace App\Filament\Resources\Payroll\Jabatan\Pages;

use App\Filament\Resources\Payroll\Jabatan\JabatanResource;
use Filament\Resources\Pages\EditRecord;

class EditJabatan extends EditRecord
{
    protected static string $resource = JabatanResource::class;

    protected function getHeaderActions(): array
    {
        // Guard FK restrict: tolak rapi bila jabatan masih dipakai penugasan.
        return [JabatanResource::guardedDeleteAction()];
    }
}
