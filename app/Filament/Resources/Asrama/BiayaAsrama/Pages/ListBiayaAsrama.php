<?php

namespace App\Filament\Resources\Asrama\BiayaAsrama\Pages;

use App\Filament\Resources\Asrama\BiayaAsrama\BiayaAsramaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBiayaAsrama extends ListRecords
{
    protected static string $resource = BiayaAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

