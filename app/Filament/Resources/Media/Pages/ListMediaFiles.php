<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaFileResource;
use App\Filament\Widgets\StorageQuotaWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMediaFiles extends ListRecords
{
    protected static string $resource = MediaFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Unggah File'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StorageQuotaWidget::class,
        ];
    }
}
