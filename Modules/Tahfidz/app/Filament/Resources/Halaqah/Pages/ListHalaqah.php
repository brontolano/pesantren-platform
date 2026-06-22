<?php

namespace Modules\Tahfidz\Filament\Resources\Halaqah\Pages;

use Modules\Tahfidz\Filament\Resources\Halaqah\HalaqahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHalaqah extends ListRecords
{
    protected static string $resource = HalaqahResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Halaqah')];
    }
}
