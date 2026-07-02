<?php

namespace Modules\Mutu\Filament\Resources\MutuSasaran\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Mutu\Filament\Resources\MutuSasaranResource;

class ListMutuSasaran extends ListRecords
{
    protected static string $resource = MutuSasaranResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Sasaran')];
    }
}
