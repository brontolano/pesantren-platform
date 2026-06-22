<?php

namespace Modules\Akademik\Filament\Resources\Kurikulum\Pages;

use Modules\Akademik\Filament\Resources\Kurikulum\KurikulumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKurikulum extends ListRecords
{
    protected static string $resource = KurikulumResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kurikulum')];
    }
}
