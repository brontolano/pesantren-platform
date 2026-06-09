<?php

namespace App\Filament\Resources\Document\Document\Pages;

use App\Filament\Resources\Document\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = Auth::id();
        $data['versi'] = 1;

        return $data;
    }
}
