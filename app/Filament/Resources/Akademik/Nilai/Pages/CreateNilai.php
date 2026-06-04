<?php

namespace App\Filament\Resources\Akademik\Nilai\Pages;

use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Akademik\Services\NilaiService;

class CreateNilai extends CreateRecord
{
    protected static string $resource = NilaiResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(NilaiService::class)->simpan($data, auth()->user());
    }
}
