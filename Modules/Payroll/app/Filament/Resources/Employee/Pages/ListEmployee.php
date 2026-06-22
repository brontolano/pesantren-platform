<?php
namespace Modules\Payroll\Filament\Resources\Employee\Pages;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListEmployee extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Tambah Pegawai')]; }
}
