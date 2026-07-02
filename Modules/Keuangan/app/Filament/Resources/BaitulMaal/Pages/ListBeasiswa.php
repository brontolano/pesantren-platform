<?php

namespace Modules\Keuangan\Filament\Resources\BaitulMaal\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\BaitulMaal\BeasiswaResource;

class ListBeasiswa extends ListRecords
{
    protected static string $resource = BeasiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Beasiswa')];
    }
}
