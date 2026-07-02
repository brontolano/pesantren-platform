<?php

namespace Modules\Kesantrian\Filament\Resources\PenempatanKelas\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesantrian\Filament\Resources\PenempatanKelas\PenempatanKelasResource;

class ListPenempatanKelas extends ListRecords
{
    protected static string $resource = PenempatanKelasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
