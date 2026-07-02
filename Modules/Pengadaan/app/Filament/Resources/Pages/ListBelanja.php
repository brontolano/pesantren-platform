<?php

namespace Modules\Pengadaan\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pengadaan\Filament\Resources\BelanjaResource;

class ListBelanja extends ListRecords
{
    protected static string $resource = BelanjaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Catat Belanja')];
    }
}
