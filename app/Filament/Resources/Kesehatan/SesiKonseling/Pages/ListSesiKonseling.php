<?php

namespace App\Filament\Resources\Kesehatan\SesiKonseling\Pages;

use App\Filament\Resources\Kesehatan\SesiKonselingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSesiKonseling extends ListRecords
{
    protected static string $resource = SesiKonselingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
