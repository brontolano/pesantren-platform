<?php

namespace Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource\Pages;

use Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenilaianTahfidz extends ListRecords
{
    protected static string $resource = PenilaianTahfidzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
