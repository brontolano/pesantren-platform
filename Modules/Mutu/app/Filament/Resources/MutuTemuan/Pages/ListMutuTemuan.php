<?php

namespace Modules\Mutu\Filament\Resources\MutuTemuan\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Mutu\Filament\Resources\MutuTemuanResource;

class ListMutuTemuan extends ListRecords
{
    protected static string $resource = MutuTemuanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Catat Temuan')];
    }
}
