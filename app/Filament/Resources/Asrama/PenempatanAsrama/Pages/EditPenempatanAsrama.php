<?php

namespace App\Filament\Resources\Asrama\PenempatanAsrama\Pages;

use App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPenempatanAsrama extends EditRecord
{
    protected static string $resource = PenempatanAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

