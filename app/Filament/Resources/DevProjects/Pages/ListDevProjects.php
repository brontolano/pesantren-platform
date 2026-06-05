<?php

namespace App\Filament\Resources\DevProjects\Pages;

use App\Filament\Resources\DevProjects\DevProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDevProjects extends ListRecords
{
    protected static string $resource = DevProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
