<?php

namespace Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiket\Pages;

use Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAreaPiket extends ListRecords
{
    protected static string $resource = AreaPiketResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
