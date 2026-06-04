<?php

namespace App\Filament\Resources\Asrama\PenempatanAsrama\Pages;

use App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Asrama\Services\PenempatanAsramaService;

class CreatePenempatanAsrama extends CreateRecord
{
    protected static string $resource = PenempatanAsramaResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(PenempatanAsramaService::class)->tempatkan($data['santri_id'], $data['kamar_id'], $data);
    }
}

