<?php

namespace App\Filament\Resources\Mutu\MutuInspeksi\Pages;

use App\Filament\Resources\Mutu\MutuInspeksiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMutuInspeksi extends ListRecords
{
    protected static string $resource = MutuInspeksiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Inspeksi')];
    }
}
