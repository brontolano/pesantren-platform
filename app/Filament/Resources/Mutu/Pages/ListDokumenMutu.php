<?php

namespace App\Filament\Resources\Mutu\Pages;

use App\Filament\Resources\Mutu\DokumenMutuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDokumenMutu extends ListRecords
{
    protected static string $resource = DokumenMutuResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
