<?php

namespace Modules\Mutu\Filament\Resources\MutuInspeksi\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Mutu\Filament\Resources\MutuInspeksiResource;

class ListMutuInspeksi extends ListRecords
{
    protected static string $resource = MutuInspeksiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Inspeksi')];
    }
}
