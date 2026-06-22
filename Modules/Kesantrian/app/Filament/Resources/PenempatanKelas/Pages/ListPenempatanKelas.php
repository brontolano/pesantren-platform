<?php

namespace Modules\Kesantrian\Filament\Resources\PenempatanKelas\Pages;

use Modules\Kesantrian\Filament\Resources\PenempatanKelas\PenempatanKelasResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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

