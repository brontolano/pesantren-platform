<?php

namespace Modules\Akademik\Filament\Resources\Lembaga\Pages;

use Modules\Akademik\Filament\Resources\Lembaga\LembagaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLembaga extends ListRecords
{
    protected static string $resource = LembagaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Lembaga')];
    }
}
