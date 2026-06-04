<?php

namespace App\Filament\Resources\Kesehatan\RiwayatKesehatan\Pages;

use App\Filament\Resources\Kesehatan\RiwayatKesehatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRiwayatKesehatan extends ListRecords
{
    protected static string $resource = RiwayatKesehatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
