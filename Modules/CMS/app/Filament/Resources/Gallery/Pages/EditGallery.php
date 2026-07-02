<?php

namespace Modules\CMS\Filament\Resources\Gallery\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\CMS\Filament\Resources\Gallery\Concerns\SyncsGalleryPhotos;
use Modules\CMS\Filament\Resources\Gallery\GalleryResource;

class EditGallery extends EditRecord
{
    use SyncsGalleryPhotos;

    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        $this->captureStagedPhotos($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistStagedPhotos();
    }
}
