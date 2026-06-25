<?php

namespace Modules\Asrama\Filament\Resources\BlokAsrama\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;

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
