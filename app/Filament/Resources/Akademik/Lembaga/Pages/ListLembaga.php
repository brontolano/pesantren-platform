<?php

namespace App\Filament\Resources\Akademik\Lembaga\Pages;

use App\Filament\Resources\Akademik\Lembaga\LembagaResource;
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
