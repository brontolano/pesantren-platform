<?php

namespace App\Filament\Resources\Document\Document\Pages;

use App\Filament\Resources\Document\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocument extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Unggah Dokumen')];
    }
}
