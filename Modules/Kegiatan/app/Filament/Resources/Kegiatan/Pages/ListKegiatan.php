<?php

namespace Modules\Kegiatan\Filament\Resources\Kegiatan\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kegiatan\Filament\Resources\KegiatanResource;

class ListKegiatan extends ListRecords
{
    protected static string $resource = KegiatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
