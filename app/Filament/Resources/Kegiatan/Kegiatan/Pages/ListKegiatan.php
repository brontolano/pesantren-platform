<?php

namespace App\Filament\Resources\Kegiatan\Kegiatan\Pages;

use App\Filament\Resources\Kegiatan\KegiatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKegiatan extends ListRecords
{
    protected static string $resource = KegiatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
