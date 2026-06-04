<?php

namespace App\Filament\Resources\Akademik\PeriodeAkademik\Pages;

use App\Filament\Resources\Akademik\PeriodeAkademik\PeriodeAkademikResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Akademik\Services\PeriodeAkademikService;

class CreatePeriodeAkademik extends CreateRecord
{
    protected static string $resource = PeriodeAkademikResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(PeriodeAkademikService::class)->simpan($data);
    }
}

