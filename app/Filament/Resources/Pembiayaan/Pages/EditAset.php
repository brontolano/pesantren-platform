<?php

namespace App\Filament\Resources\Pembiayaan\Pages;

use App\Filament\Resources\Pembiayaan\AsetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAset extends EditRecord
{
    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
