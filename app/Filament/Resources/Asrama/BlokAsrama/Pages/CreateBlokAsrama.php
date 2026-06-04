<?php

namespace App\Filament\Resources\Asrama\BlokAsrama\Pages;

use App\Filament\Resources\Asrama\BlokAsrama\BlokAsramaResource;
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

