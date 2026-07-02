<?php

namespace Modules\Payroll\Filament\Resources\Pelamar\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Filament\Resources\Pelamar\PelamarResource;

class ListPelamar extends ListRecords
{
    protected static string $resource = PelamarResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Pelamar')];
    }
}
