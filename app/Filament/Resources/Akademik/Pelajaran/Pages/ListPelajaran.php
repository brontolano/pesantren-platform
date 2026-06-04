<?php

namespace App\Filament\Resources\Akademik\Pelajaran\Pages;

use App\Filament\Resources\Akademik\Pelajaran\PelajaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPelajaran extends ListRecords
{
    protected static string $resource = PelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

