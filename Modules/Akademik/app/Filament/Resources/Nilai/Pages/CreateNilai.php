<?php

namespace Modules\Akademik\Filament\Resources\Nilai\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Filament\Resources\Nilai\NilaiResource;
use Modules\Akademik\Services\NilaiService;

class CreateNilai extends CreateRecord
{
    protected static string $resource = NilaiResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(NilaiService::class)->simpan($data, auth()->user());
    }
}
