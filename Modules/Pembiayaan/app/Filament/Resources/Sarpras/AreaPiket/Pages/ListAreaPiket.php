<?php

namespace Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiket\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiketResource;

class ListAreaPiket extends ListRecords
{
    protected static string $resource = AreaPiketResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
