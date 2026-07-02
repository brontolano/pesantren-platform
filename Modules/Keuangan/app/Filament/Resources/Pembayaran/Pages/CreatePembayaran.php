<?php

namespace Modules\Keuangan\Filament\Resources\Pembayaran\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Keuangan\Filament\Resources\Pembayaran\PembayaranResource;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;

class CreatePembayaran extends CreateRecord
{
    protected static string $resource = PembayaranResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Auto-fill santri_id dari tagihan
        if (! empty($data['tagihan_id']) && empty($data['santri_id'])) {
            $tagihan = Tagihan::find($data['tagihan_id']);
            $data['santri_id'] = $tagihan?->santri_id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Update status tagihan setelah pembayaran dibuat
        $pembayaran = $this->record;
        if ($pembayaran->tagihan) {
            (new PembayaranService)->updateStatusTagihan($pembayaran->tagihan);
        }
    }
}
