<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\DivisiUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDivisiUnit extends EditRecord
{
    protected static string $resource = DivisiUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
