<?php

namespace Modules\Asrama\Filament\Resources\BiayaAsrama\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\BiayaAsrama\BiayaAsramaResource;
use Modules\Asrama\Services\BiayaAsramaService;

class CreateBiayaAsrama extends CreateRecord
{
    protected static string $resource = BiayaAsramaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BiayaAsramaService::class)->sinkronkan($data);
    }
}
