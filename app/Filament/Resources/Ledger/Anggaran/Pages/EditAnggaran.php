<?php

namespace App\Filament\Resources\Ledger\Anggaran\Pages;

use App\Filament\Resources\Ledger\AnggaranResource;
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
