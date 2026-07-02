<?php

namespace Modules\Kegiatan\Filament\Resources\Template\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Kegiatan\Filament\Resources\Template\KegiatanTemplateResource;

class EditKegiatanTemplate extends EditRecord
{
    protected static string $resource = KegiatanTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
