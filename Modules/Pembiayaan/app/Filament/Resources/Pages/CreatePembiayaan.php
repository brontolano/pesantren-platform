<?php

namespace Modules\Pembiayaan\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Pembiayaan\Filament\Resources\PembiayaanResource;
use Modules\Pembiayaan\Services\PembiayaanService;

class CreatePembiayaan extends CreateRecord
{
    protected static string $resource = PembiayaanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['created_by'] = auth()->id();

        return app(PembiayaanService::class)->ajukan($data);
    }
}
