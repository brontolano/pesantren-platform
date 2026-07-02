<?php

namespace Modules\CMS\Filament\Resources\Gallery\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\CMS\Filament\Resources\Gallery\GalleryResource;

class ListGalleries extends ListRecords
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
