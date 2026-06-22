<?php

namespace Modules\Keuangan\Filament\Resources\SetorKeliling\Pages;

use Modules\Keuangan\Filament\Resources\SetorKeliling\SetorKelilingResource;
use Filament\Resources\Pages\ListRecords;

class ListSetorKeliling extends ListRecords
{
    protected static string $resource = SetorKelilingResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Buka Sesi Keliling')];
    }
}
