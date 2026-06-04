<?php

namespace App\Filament\Resources\Kegiatan\KunjunganWali\Pages;

use App\Filament\Resources\Kegiatan\KunjunganWaliResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKunjunganWali extends ListRecords
{
    protected static string $resource = KunjunganWaliResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
