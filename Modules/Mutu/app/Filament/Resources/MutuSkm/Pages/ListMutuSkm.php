<?php

namespace Modules\Mutu\Filament\Resources\MutuSkm\Pages;

use Modules\Mutu\Filament\Resources\MutuSkmResource;
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
