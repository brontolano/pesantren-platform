<?php

namespace Modules\Tahfidz\Filament\Resources\SimaAnResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Tahfidz\Filament\Resources\SimaAnResource;

class ListSimaAn extends ListRecords
{
    protected static string $resource = SimaAnResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
