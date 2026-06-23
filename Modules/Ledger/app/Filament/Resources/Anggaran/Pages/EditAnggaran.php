<?php

namespace Modules\Ledger\Filament\Resources\Anggaran\Pages;

use Modules\Ledger\Filament\Resources\AnggaranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnggaran extends EditRecord
{
    protected static string $resource = AnggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
