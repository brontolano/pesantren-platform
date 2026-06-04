<?php

namespace App\Filament\Resources\Tahfidz\HafalanTahfidzResource\Pages;

use App\Filament\Resources\Tahfidz\HafalanTahfidzResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHafalanTahfidz extends ListRecords
{
    protected static string $resource = HafalanTahfidzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
