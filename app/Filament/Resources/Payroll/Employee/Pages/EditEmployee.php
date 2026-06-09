<?php
namespace App\Filament\Resources\Payroll\Employee\Pages;
use App\Filament\Resources\Payroll\Employee\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
