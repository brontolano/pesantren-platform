<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\AkunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAkun extends EditRecord
{
    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
