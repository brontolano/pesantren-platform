<?php

namespace Modules\Kegiatan\Filament\Resources\Template\Pages;

use Modules\Kegiatan\Filament\Resources\Template\KegiatanTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKegiatanTemplate extends EditRecord
{
    protected static string $resource = KegiatanTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
