<?php

namespace App\Filament\Resources\Tagihan\Pages;

use App\Filament\Resources\Tagihan\TagihanResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Keuangan\Services\TagihanService;

class CreateTagihan extends CreateRecord
{
    protected static string $resource = TagihanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $nominal = (float) ($data['nominal'] ?? 0);
        $diskon = (float) ($data['diskon'] ?? 0);
        $data['nominal_akhir'] = max($nominal - $diskon, 0);
        $data['created_by'] = auth()->id();

        return $data;
    }
}

