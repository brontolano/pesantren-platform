<?php

namespace Modules\Asrama\Filament\Resources\KamarAsrama\Pages;

use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKamarAsrama extends ListRecords
{
    protected static string $resource = KamarAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

