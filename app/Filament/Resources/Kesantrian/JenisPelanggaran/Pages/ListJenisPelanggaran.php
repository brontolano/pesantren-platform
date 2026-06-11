<?php

namespace App\Filament\Resources\Kesantrian\JenisPelanggaran\Pages;

use App\Filament\Resources\Kesantrian\JenisPelanggaran\JenisPelanggaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisPelanggaran extends ListRecords
{
    protected static string $resource = JenisPelanggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Jenis')];
    }
}
