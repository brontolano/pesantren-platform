<?php

namespace App\Filament\Resources\Kegiatan\Template\Pages;

use App\Filament\Resources\Kegiatan\Template\KegiatanTemplateResource;
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
