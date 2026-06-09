<?php

namespace App\Filament\Resources\Mutu\MutuTemuan\Pages;

use App\Filament\Resources\Mutu\MutuTemuanResource;
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
