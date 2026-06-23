<?php

namespace Modules\Kegiatan\Filament\Resources\Kegiatan\Pages;

use Modules\Kegiatan\Filament\Resources\KegiatanResource;
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
