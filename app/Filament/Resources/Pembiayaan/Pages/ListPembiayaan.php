<?php

namespace App\Filament\Resources\Pembiayaan\Pages;

use App\Filament\Resources\Pembiayaan\PembiayaanResource;
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
