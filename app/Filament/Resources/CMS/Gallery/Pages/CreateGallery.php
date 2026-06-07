<?php

namespace App\Filament\Resources\CMS\Gallery\Pages;

use App\Filament\Resources\CMS\Gallery\Concerns\SyncsGalleryPhotos;
use App\Filament\Resources\CMS\Gallery\GalleryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGallery extends CreateRecord
{
    use SyncsGalleryPhotos;

    protected static string $resource = GalleryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $this->captureStagedPhotos($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistStagedPhotos();
    }
}
