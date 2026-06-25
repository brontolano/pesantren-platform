<?php

namespace Modules\Ledger\Filament\Resources\KursValuta\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Filament\Resources\KursValuta\KursValutaResource;

class ListKursValuta extends ListRecords
{
    protected static string $resource = KursValutaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kurs')];
    }
}
