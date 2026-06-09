<?php

namespace App\Filament\Resources\Kegiatan\Template\Pages;

use App\Filament\Resources\Kegiatan\Template\KegiatanTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKegiatanTemplate extends ListRecords
{
    protected static string $resource = KegiatanTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Template')];
    }
}
