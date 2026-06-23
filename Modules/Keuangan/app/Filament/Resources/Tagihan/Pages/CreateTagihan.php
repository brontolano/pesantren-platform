<?php

namespace Modules\Keuangan\Filament\Resources\Tagihan\Pages;

use Modules\Keuangan\Filament\Resources\Tagihan\TagihanResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\BeasiswaService;

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

    protected function afterCreate(): void
    {
        // 2c — beasiswa potong SPP: terapkan potongan dana sosial pada tagihan
        // SPP yang baru dibuat (idempoten; no-op untuk non-SPP/non-penerima).
        if ($this->record instanceof Tagihan) {
            app(BeasiswaService::class)->terapkanPada($this->record);
        }
    }
}
