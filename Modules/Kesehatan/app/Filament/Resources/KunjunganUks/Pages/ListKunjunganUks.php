<?php

namespace Modules\Kesehatan\Filament\Resources\KunjunganUks\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesehatan\Filament\Resources\KunjunganUksResource;

class ListKunjunganUks extends ListRecords
{
    protected static string $resource = KunjunganUksResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
