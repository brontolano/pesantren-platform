<?php

namespace App\Filament\Resources\Payroll\Pelamar\Pages;

use App\Filament\Resources\Payroll\Pelamar\PelamarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPelamar extends ListRecords
{
    protected static string $resource = PelamarResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Pelamar')];
    }
}
