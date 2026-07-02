<?php

namespace Modules\Keuangan\Filament\Resources\TagihanRutin\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Keuangan\Filament\Resources\TagihanRutin\TagihanRutinResource;

class CreateTagihanRutin extends CreateRecord
{
    protected static string $resource = TagihanRutinResource::class;

    /** #332: nominal = total komponen bila rincian diisi. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TagihanRutinResource::sinkronkanKomponen($data);
    }
}
