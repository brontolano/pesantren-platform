<?php

namespace Modules\Tahfidz\Filament\Resources\TahfidzTarget\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Tahfidz\Filament\Resources\TahfidzTarget\TahfidzTargetResource;

class ListTahfidzTarget extends ListRecords
{
    protected static string $resource = TahfidzTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Target')];
    }
}
