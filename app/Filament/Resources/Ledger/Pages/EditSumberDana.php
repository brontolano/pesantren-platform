<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\SumberDanaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSumberDana extends EditRecord
{
    protected static string $resource = SumberDanaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
