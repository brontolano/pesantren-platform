<?php

namespace Modules\Ledger\Filament\Resources\KursValuta\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Ledger\Filament\Resources\KursValuta\KursValutaResource;

class EditKursValuta extends EditRecord
{
    protected static string $resource = KursValutaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
