<?php

namespace Modules\Asrama\Filament\Resources\BlokAsrama\Pages;

use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Asrama\Services\BlokAsramaService;

class CreateBlokAsrama extends CreateRecord
{
    protected static string $resource = BlokAsramaResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(BlokAsramaService::class)->simpan($data);
    }
}

