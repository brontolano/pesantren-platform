<?php

namespace Modules\Asrama\Filament\Resources\BlokAsrama\Pages;

use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlokAsrama extends ListRecords
{
    protected static string $resource = BlokAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

