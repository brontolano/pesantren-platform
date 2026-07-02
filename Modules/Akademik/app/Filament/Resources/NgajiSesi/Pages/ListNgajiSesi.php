<?php

namespace Modules\Akademik\Filament\Resources\NgajiSesi\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\NgajiSesi\NgajiSesiResource;

class ListNgajiSesi extends ListRecords
{
    protected static string $resource = NgajiSesiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
