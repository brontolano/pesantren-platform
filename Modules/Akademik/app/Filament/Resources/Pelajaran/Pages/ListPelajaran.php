<?php

namespace Modules\Akademik\Filament\Resources\Pelajaran\Pages;

use Modules\Akademik\Filament\Resources\Pelajaran\PelajaranResource;
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

