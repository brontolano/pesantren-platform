<?php

namespace Modules\Akademik\Filament\Resources\Lembaga\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\Lembaga\LembagaResource;

class ListLembaga extends ListRecords
{
    protected static string $resource = LembagaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Lembaga')];
    }
}
