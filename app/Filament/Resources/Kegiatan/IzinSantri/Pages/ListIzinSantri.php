<?php

namespace App\Filament\Resources\Kegiatan\IzinSantri\Pages;

use App\Filament\Resources\Kegiatan\IzinSantriResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIzinSantri extends ListRecords
{
    protected static string $resource = IzinSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
