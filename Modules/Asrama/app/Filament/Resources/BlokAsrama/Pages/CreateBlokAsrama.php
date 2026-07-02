<?php

namespace Modules\Asrama\Filament\Resources\BlokAsrama\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Modules\Asrama\Services\BlokAsramaService;

class CreateBlokAsrama extends CreateRecord
{
    protected static string $resource = BlokAsramaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BlokAsramaService::class)->simpan($data);
    }
}
