<?php

namespace Modules\Kegiatan\Filament\Resources\IzinSantri\Pages;

use Modules\Kegiatan\Filament\Resources\IzinSantriResource;
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
