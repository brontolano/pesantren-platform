<?php

namespace Modules\Keuangan\Filament\Resources\Pos\Pages;

use Modules\Keuangan\Filament\Resources\Pos\PosKategoriResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosKategori extends ListRecords
{
    protected static string $resource = PosKategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
