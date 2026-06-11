<?php

namespace App\Filament\Resources\Akademik\PaketSoalAkademik\Pages;

use App\Filament\Resources\Akademik\PaketSoalAkademik\PaketSoalAkademikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaketSoalAkademik extends ListRecords
{
    protected static string $resource = PaketSoalAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
