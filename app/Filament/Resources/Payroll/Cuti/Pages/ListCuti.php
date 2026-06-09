<?php
namespace App\Filament\Resources\Payroll\Cuti\Pages;
use App\Filament\Resources\Payroll\Cuti\CutiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListCuti extends ListRecords
{
    protected static string $resource = CutiResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Ajukan Cuti')]; }
}
