<?php

namespace App\Filament\Resources\Tahfidz\PenilaianTahfidzResource\Pages;

use App\Filament\Resources\Tahfidz\PenilaianTahfidzResource;
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
