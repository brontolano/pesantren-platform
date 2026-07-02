<?php

namespace Modules\Pengadaan\Filament\Resources\PurchaseOrder\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\PurchaseOrderResource;
use Modules\Pengadaan\Services\PurchaseOrderService;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PurchaseOrderService::class)->buat((int) $data['supplier_id'], $data['items'] ?? [], [
            'tanggal' => $data['tanggal'] ?? null,
            'divisi_id' => $data['divisi_id'] ?? null,
            'sumber_dana_id' => $data['sumber_dana_id'] ?? null,
            'akun_beban_id' => $data['akun_beban_id'] ?? null,
            'jenis' => $data['jenis'] ?? 'barang',
            'keterangan' => $data['keterangan'] ?? null,
            'dibuat_oleh' => Auth::id(),
        ]);
    }
}
