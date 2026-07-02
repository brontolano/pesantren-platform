<?php

namespace Modules\Kegiatan\Filament\Resources\KunjunganWali\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kegiatan\Filament\Resources\KunjunganWaliResource;

class ListKunjunganWali extends ListRecords
{
    protected static string $resource = KunjunganWaliResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
