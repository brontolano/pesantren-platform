<?php

namespace App\Filament\Resources\DevProjects\Pages;

use App\Filament\Resources\DevProjects\DevProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDevProject extends EditRecord
{
    protected static string $resource = DevProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
