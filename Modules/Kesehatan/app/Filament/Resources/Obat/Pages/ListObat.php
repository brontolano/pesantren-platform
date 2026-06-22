<?php

namespace Modules\Kesehatan\Filament\Resources\Obat\Pages;

use Modules\Kesehatan\Filament\Resources\ObatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListObat extends ListRecords
{
    protected static string $resource = ObatResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
