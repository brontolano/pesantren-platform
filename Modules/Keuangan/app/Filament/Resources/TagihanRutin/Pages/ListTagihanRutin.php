<?php

namespace Modules\Keuangan\Filament\Resources\TagihanRutin\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\TagihanRutin\TagihanRutinResource;

class ListTagihanRutin extends ListRecords
{
    protected static string $resource = TagihanRutinResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Tagihan Rutin')];
    }
}
