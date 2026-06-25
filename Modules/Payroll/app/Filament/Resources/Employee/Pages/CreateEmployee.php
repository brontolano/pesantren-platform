<?php

namespace Modules\Payroll\Filament\Resources\Employee\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
