<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Ledger\Filament\Resources\DivisiUnitResource;

class EditDivisiUnit extends EditRecord
{
    protected static string $resource = DivisiUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
