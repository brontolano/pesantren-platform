<?php

namespace Modules\Asrama\Filament\Resources\KamarAsrama\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Modules\Asrama\Filament\Widgets\OkupansiAsramaWidget;

class ListKamarAsrama extends ListRecords
{
    protected static string $resource = KamarAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OkupansiAsramaWidget::class,
        ];
    }
}
