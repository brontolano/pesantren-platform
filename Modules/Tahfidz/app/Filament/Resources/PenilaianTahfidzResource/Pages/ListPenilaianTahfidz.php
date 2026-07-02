<?php

namespace Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource;

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
