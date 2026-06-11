<?php

namespace App\Filament\Resources\Akademik\KalenderAkademik\Pages;

use App\Filament\Resources\Akademik\KalenderAkademik\KalenderAkademikResource;
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
