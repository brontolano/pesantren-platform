<?php

namespace Modules\Akademik\Filament\Resources\PeriodeAkademik\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\PeriodeAkademikResource;

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
