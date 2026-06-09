<?php

namespace App\Filament\Resources\Mutu\MutuSasaran\Pages;

use App\Filament\Resources\Mutu\MutuSasaranResource;
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
