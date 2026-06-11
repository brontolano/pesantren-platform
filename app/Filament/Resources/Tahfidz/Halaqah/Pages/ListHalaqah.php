<?php

namespace App\Filament\Resources\Tahfidz\Halaqah\Pages;

use App\Filament\Resources\Tahfidz\Halaqah\HalaqahResource;
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
