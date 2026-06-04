<?php

namespace App\Filament\Resources\Asrama\BiayaAsrama\Pages;

use App\Filament\Resources\Asrama\BiayaAsrama\BiayaAsramaResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Asrama\Services\BiayaAsramaService;

class CreateBiayaAsrama extends CreateRecord
{
    protected static string $resource = BiayaAsramaResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(BiayaAsramaService::class)->sinkronkan($data);
    }
}

