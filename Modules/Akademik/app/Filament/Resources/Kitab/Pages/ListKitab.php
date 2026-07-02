<?php

namespace Modules\Akademik\Filament\Resources\Kitab\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\Kitab\KitabResource;

class ListKitab extends ListRecords
{
    protected static string $resource = KitabResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kitab')];
    }
}
