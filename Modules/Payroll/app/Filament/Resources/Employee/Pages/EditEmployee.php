<?php

namespace Modules\Payroll\Filament\Resources\Employee\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
