<?php
namespace Modules\Payroll\Filament\Resources\Employee\Pages;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
