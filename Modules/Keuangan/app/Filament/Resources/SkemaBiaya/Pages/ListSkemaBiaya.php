<?php

namespace Modules\Keuangan\Filament\Resources\SkemaBiaya\Pages;

use Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSkemaBiaya extends ListRecords
{
    protected static string $resource = SkemaBiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Skema')];
    }
}
