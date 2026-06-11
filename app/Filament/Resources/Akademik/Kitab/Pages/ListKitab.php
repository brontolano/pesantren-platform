<?php

namespace App\Filament\Resources\Akademik\Kitab\Pages;

use App\Filament\Resources\Akademik\Kitab\KitabResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKitab extends ListRecords
{
    protected static string $resource = KitabResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kitab')];
    }
}
