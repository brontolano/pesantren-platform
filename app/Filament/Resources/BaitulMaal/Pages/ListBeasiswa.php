<?php

namespace App\Filament\Resources\BaitulMaal\Pages;

use App\Filament\Resources\BaitulMaal\BeasiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBeasiswa extends ListRecords
{
    protected static string $resource = BeasiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Beasiswa')];
    }
}
