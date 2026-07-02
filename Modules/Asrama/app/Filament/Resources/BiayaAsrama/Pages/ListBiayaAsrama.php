<?php

namespace Modules\Asrama\Filament\Resources\BiayaAsrama\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Asrama\Filament\Resources\BiayaAsrama\BiayaAsramaResource;

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
