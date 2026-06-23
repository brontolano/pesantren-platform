<?php
namespace Modules\Payroll\Filament\Resources\Employee\Pages;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
class CreateEmployee extends CreateRecord { protected static string $resource = EmployeeResource::class; }
