<?php

namespace Modules\Mutu\Filament\Resources\MutuTemuan\Pages;

use Modules\Mutu\Filament\Resources\MutuTemuanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMutuTemuan extends ListRecords
{
    protected static string $resource = MutuTemuanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Catat Temuan')];
    }
}
