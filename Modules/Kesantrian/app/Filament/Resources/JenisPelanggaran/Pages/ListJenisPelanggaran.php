<?php

namespace Modules\Kesantrian\Filament\Resources\JenisPelanggaran\Pages;

use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\JenisPelanggaranResource;
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
