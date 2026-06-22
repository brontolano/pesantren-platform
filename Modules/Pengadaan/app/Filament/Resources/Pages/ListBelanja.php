<?php

namespace Modules\Pengadaan\Filament\Resources\Pages;

use Modules\Pengadaan\Filament\Resources\BelanjaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBelanja extends ListRecords
{
    protected static string $resource = BelanjaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Catat Belanja')];
    }
}
