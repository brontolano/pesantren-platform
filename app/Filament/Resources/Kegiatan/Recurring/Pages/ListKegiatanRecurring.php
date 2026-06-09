<?php

namespace App\Filament\Resources\Kegiatan\Recurring\Pages;

use App\Filament\Resources\Kegiatan\Recurring\KegiatanRecurringResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKegiatanRecurring extends ListRecords
{
    protected static string $resource = KegiatanRecurringResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Jadwal Berulang')];
    }
}
