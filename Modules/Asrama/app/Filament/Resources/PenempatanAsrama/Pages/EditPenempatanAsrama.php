<?php

namespace Modules\Asrama\Filament\Resources\PenempatanAsrama\Pages;

use Modules\Asrama\Filament\Resources\PenempatanAsrama\PenempatanAsramaResource;
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

