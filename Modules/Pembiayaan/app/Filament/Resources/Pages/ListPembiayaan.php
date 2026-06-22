<?php

namespace Modules\Pembiayaan\Filament\Resources\Pages;

use Modules\Pembiayaan\Filament\Resources\PembiayaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPembiayaan extends ListRecords
{
    protected static string $resource = PembiayaanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajukan Pembiayaan')];
    }
}
