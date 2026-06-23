<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Modules\Ledger\Filament\Resources\SumberDanaResource;
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
