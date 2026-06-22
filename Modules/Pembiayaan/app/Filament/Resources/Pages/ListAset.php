<?php

namespace Modules\Pembiayaan\Filament\Resources\Pages;

use Modules\Pembiayaan\Filament\Resources\AsetResource;
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
