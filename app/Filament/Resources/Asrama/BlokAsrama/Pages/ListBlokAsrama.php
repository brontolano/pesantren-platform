<?php

namespace App\Filament\Resources\Asrama\BlokAsrama\Pages;

use App\Filament\Resources\Asrama\BlokAsrama\BlokAsramaResource;
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

