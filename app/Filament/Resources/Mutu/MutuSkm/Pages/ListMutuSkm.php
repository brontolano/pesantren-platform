<?php

namespace App\Filament\Resources\Mutu\MutuSkm\Pages;

use App\Filament\Resources\Mutu\MutuSkmResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMutuSkm extends ListRecords
{
    protected static string $resource = MutuSkmResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Respons SKM')];
    }
}
