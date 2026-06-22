<?php

namespace Modules\Kesehatan\Filament\Resources\RiwayatKesehatan\Pages;

use Modules\Kesehatan\Filament\Resources\RiwayatKesehatanResource;
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
