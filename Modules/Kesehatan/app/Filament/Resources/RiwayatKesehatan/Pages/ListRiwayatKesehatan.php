<?php

namespace Modules\Kesehatan\Filament\Resources\RiwayatKesehatan\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatanResource;

class ListRiwayatKesehatan extends ListRecords
{
    protected static string $resource = RiwayatKesehatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
