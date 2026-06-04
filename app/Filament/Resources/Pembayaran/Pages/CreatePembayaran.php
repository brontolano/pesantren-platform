<?php

namespace App\Filament\Resources\Pembayaran\Pages;

use App\Filament\Resources\Pembayaran\PembayaranResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePembayaran extends CreateRecord
{
    protected static string $resource = PembayaranResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Auto-fill santri_id dari tagihan
        if (! empty($data['tagihan_id']) && empty($data['santri_id'])) {
            $tagihan = \Modules\Keuangan\Models\Tagihan::find($data['tagihan_id']);
            $data['santri_id'] = $tagihan?->santri_id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Update status tagihan setelah pembayaran dibuat
        $pembayaran = $this->record;
        if ($pembayaran->tagihan) {
            (new \Modules\Keuangan\Services\PembayaranService())->updateStatusTagihan($pembayaran->tagihan);
        }
    }
}

