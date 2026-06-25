<?php

namespace Modules\Akademik\Filament\Resources\PaketSoalAkademik\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\PaketSoalAkademik\PaketSoalAkademikResource;

class ListPaketSoalAkademik extends ListRecords
{
    protected static string $resource = PaketSoalAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
