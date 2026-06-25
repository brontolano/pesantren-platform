<?php

namespace Modules\Asrama\Filament\Resources\PenempatanAsrama\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\PenempatanAsramaResource;
use Modules\Asrama\Services\PenempatanAsramaService;

class CreatePenempatanAsrama extends CreateRecord
{
    protected static string $resource = PenempatanAsramaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PenempatanAsramaService::class)->tempatkan($data['santri_id'], $data['kamar_id'], $data);
    }
}
