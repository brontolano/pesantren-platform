<?php

namespace App\Filament\Resources\Asrama\PenempatanAsrama\Pages;

use App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenempatanAsrama extends ListRecords
{
    protected static string $resource = PenempatanAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

