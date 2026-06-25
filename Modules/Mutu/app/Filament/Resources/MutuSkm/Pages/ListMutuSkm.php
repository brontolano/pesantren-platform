<?php

namespace Modules\Mutu\Filament\Resources\MutuSkm\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Mutu\Filament\Resources\MutuSkmResource;

class ListMutuSkm extends ListRecords
{
    protected static string $resource = MutuSkmResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Respons SKM')];
    }
}
