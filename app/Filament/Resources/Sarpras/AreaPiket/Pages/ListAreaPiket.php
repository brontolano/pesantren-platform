<?php

namespace App\Filament\Resources\Sarpras\AreaPiket\Pages;

use App\Filament\Resources\Sarpras\AreaPiketResource;
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
