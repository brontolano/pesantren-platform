<?php

namespace Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource\Pages;

use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
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
