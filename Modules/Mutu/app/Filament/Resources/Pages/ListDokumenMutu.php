<?php

namespace Modules\Mutu\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Mutu\Filament\Resources\DokumenMutuResource;

class ListDokumenMutu extends ListRecords
{
    protected static string $resource = DokumenMutuResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
