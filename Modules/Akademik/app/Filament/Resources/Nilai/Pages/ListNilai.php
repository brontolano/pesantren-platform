<?php

namespace Modules\Akademik\Filament\Resources\Nilai\Pages;

use Modules\Akademik\Filament\Resources\Nilai\NilaiResource;
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

