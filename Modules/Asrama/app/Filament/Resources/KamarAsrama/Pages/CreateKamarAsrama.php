<?php

namespace Modules\Asrama\Filament\Resources\KamarAsrama\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Modules\Asrama\Services\KamarAsramaService;

class CreateKamarAsrama extends CreateRecord
{
    protected static string $resource = KamarAsramaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(KamarAsramaService::class)->simpan($data);
    }
}
