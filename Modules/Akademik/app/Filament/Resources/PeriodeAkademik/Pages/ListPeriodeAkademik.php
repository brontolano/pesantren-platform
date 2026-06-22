<?php

namespace Modules\Akademik\Filament\Resources\PeriodeAkademik\Pages;

use Modules\Akademik\Filament\Resources\PeriodeAkademik\PeriodeAkademikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPeriodeAkademik extends ListRecords
{
    protected static string $resource = PeriodeAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

