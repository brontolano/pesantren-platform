<?php

namespace Modules\Asrama\Filament\Resources\KamarAsrama\Pages;

use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Asrama\Services\KamarAsramaService;

class CreateKamarAsrama extends CreateRecord
{
    protected static string $resource = KamarAsramaResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(KamarAsramaService::class)->simpan($data);
    }
}

