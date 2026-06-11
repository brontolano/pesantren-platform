<?php

namespace App\Filament\Resources\Akademik\Kurikulum\Pages;

use App\Filament\Resources\Akademik\Kurikulum\KurikulumResource;
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
