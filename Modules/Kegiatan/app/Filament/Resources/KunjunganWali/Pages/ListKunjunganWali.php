<?php

namespace Modules\Kegiatan\Filament\Resources\KunjunganWali\Pages;

use Modules\Kegiatan\Filament\Resources\KunjunganWaliResource;
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
