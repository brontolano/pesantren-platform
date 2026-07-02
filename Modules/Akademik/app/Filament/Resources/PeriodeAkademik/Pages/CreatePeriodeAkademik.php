<?php

namespace Modules\Akademik\Filament\Resources\PeriodeAkademik\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\PeriodeAkademikResource;
use Modules\Akademik\Services\PeriodeAkademikService;

class CreatePeriodeAkademik extends CreateRecord
{
    protected static string $resource = PeriodeAkademikResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PeriodeAkademikService::class)->simpan($data);
    }
}
