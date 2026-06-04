<?php

namespace App\Filament\Resources\Akademik\PeriodeAkademik\Pages;

use App\Filament\Resources\Akademik\PeriodeAkademik\PeriodeAkademikResource;
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

