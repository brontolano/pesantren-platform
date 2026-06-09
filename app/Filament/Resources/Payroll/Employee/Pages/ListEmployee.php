<?php
namespace App\Filament\Resources\Payroll\Employee\Pages;
use App\Filament\Resources\Payroll\Employee\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListEmployee extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Tambah Pegawai')]; }
}
