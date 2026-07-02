<?php

namespace Modules\Kesehatan\Filament\Resources\KunjunganUks\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Kesehatan\Filament\Resources\KunjunganUksResource;

class CreateKunjunganUks extends CreateRecord
{
    protected static string $resource = KunjunganUksResource::class;

    /** Catat petugas pembuat (sejalan dgn PWA Guru UKS). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['pencatat_id'] ??= auth()->id();

        return $data;
    }
}
