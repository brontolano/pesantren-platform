<?php

namespace Modules\Mutu\Filament\Resources\MutuSasaran\Pages;

use Modules\Mutu\Filament\Resources\MutuSasaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMutuSasaran extends ListRecords
{
    protected static string $resource = MutuSasaranResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Sasaran')];
    }
}
