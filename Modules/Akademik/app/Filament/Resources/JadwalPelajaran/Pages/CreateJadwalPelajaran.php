<?php

namespace Modules\Akademik\Filament\Resources\JadwalPelajaran\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use Modules\Akademik\Services\JadwalPelajaranService;

class CreateJadwalPelajaran extends CreateRecord
{
    protected static string $resource = JadwalPelajaranResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(JadwalPelajaranService::class)->simpan($data);
    }
}
