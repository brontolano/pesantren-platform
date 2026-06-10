<?php

namespace App\Filament\Resources\Ledger\KursValuta\Pages;

use App\Filament\Resources\Ledger\KursValuta\KursValutaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKursValuta extends EditRecord
{
    protected static string $resource = KursValutaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
