<?php

namespace App\Filament\Resources\Pembiayaan\Pages;

use App\Filament\Resources\Pembiayaan\AsetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAset extends ListRecords
{
    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
