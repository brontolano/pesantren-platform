<?php

namespace Modules\Keuangan\Filament\Resources\SkemaBiaya\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource;

class ListSkemaBiaya extends ListRecords
{
    protected static string $resource = SkemaBiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Skema')];
    }
}
