<?php

namespace Modules\Akademik\Filament\Resources\JadwalPelajaran\Pages;

use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Akademik\Services\JadwalPelajaranService;

class CreateJadwalPelajaran extends CreateRecord
{
    protected static string $resource = JadwalPelajaranResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(JadwalPelajaranService::class)->simpan($data);
    }
}

