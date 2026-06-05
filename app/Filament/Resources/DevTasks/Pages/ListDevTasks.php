<?php

namespace App\Filament\Resources\DevTasks\Pages;

use App\Filament\Resources\DevTasks\DevTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDevTasks extends ListRecords
{
    protected static string $resource = DevTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
