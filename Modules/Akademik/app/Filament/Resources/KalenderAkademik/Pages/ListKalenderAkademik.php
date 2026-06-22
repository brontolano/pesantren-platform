<?php

namespace Modules\Akademik\Filament\Resources\KalenderAkademik\Pages;

use Modules\Akademik\Filament\Resources\KalenderAkademik\KalenderAkademikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKalenderAkademik extends ListRecords
{
    protected static string $resource = KalenderAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Entri')];
    }
}
