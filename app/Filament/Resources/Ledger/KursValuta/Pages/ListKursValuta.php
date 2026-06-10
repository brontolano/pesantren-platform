<?php

namespace App\Filament\Resources\Ledger\KursValuta\Pages;

use App\Filament\Resources\Ledger\KursValuta\KursValutaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKursValuta extends ListRecords
{
    protected static string $resource = KursValutaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kurs')];
    }
}
