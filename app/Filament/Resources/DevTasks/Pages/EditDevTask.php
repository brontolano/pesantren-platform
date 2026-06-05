<?php

namespace App\Filament\Resources\DevTasks\Pages;

use App\Filament\Resources\DevTasks\DevTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDevTask extends EditRecord
{
    protected static string $resource = DevTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
