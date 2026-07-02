<?php

namespace Modules\Notifikasi\Filament\Resources\BroadcastWa\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\BroadcastWaResource;

class ListBroadcastWa extends ListRecords
{
    protected static string $resource = BroadcastWaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
