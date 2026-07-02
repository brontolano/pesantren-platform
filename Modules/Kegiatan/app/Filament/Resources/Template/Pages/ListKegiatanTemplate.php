<?php

namespace Modules\Kegiatan\Filament\Resources\Template\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kegiatan\Filament\Resources\Template\KegiatanTemplateResource;

class ListKegiatanTemplate extends ListRecords
{
    protected static string $resource = KegiatanTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Template')];
    }
}
