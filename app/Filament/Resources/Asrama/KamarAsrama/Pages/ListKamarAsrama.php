<?php

namespace App\Filament\Resources\Asrama\KamarAsrama\Pages;

use App\Filament\Resources\Asrama\KamarAsrama\KamarAsramaResource;
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

