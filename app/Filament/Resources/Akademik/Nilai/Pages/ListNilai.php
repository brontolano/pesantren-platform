<?php

namespace App\Filament\Resources\Akademik\Nilai\Pages;

use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNilai extends ListRecords
{
    protected static string $resource = NilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

