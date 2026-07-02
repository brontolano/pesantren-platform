<?php

namespace Modules\Asrama\Filament\Resources\PenempatanAsrama\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\PenempatanAsramaResource;

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
