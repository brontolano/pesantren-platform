<?php

namespace Modules\Keuangan\Filament\Resources\SetorKeliling\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\SetorKeliling\SetorKelilingResource;

class ListSetorKeliling extends ListRecords
{
    protected static string $resource = SetorKelilingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buka Sesi Keliling')];
    }
}
