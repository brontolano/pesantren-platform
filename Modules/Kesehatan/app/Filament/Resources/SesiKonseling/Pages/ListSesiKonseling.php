<?php

namespace Modules\Kesehatan\Filament\Resources\SesiKonseling\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesehatan\Filament\Resources\SesiKonselingResource;

class ListSesiKonseling extends ListRecords
{
    protected static string $resource = SesiKonselingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
