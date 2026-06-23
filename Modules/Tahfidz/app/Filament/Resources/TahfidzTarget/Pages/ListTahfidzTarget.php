<?php

namespace Modules\Tahfidz\Filament\Resources\TahfidzTarget\Pages;

use Modules\Tahfidz\Filament\Resources\TahfidzTarget\TahfidzTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTahfidzTarget extends ListRecords
{
    protected static string $resource = TahfidzTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Target')];
    }
}
