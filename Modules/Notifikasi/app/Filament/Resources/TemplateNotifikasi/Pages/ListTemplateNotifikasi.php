<?php

namespace Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\TemplateNotifikasiResource;

class ListTemplateNotifikasi extends ListRecords
{
    protected static string $resource = TemplateNotifikasiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
