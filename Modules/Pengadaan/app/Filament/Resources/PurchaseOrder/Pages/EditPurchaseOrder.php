<?php

namespace Modules\Pengadaan\Filament\Resources\PurchaseOrder\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\PurchaseOrderResource;
use Modules\Pengadaan\Models\PurchaseOrder;
use Modules\Pengadaan\Services\PurchaseOrderService;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    /** gap-95: muat item PO ke form agar tampil & editable (PO draft). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof PurchaseOrder) {
            $data['items'] = $record->items()
                ->orderBy('id')
                ->get(['uraian', 'qty', 'harga_satuan'])
                ->map(fn ($i) => [
                    'uraian' => $i->uraian,
                    'qty' => (float) $i->qty,
                    'harga_satuan' => (float) $i->harga_satuan,
                ])
                ->all();
        }

        return $data;
    }

    /**
     * gap-95: simpan field PO via update biasa, lalu serahkan item ke service
     * (rumus total dari PurchaseOrderService, bukan rumus baru). Item hanya
     * diproses untuk PO draft; PO non-draft tidak mengirim item (repeater
     * tersembunyi) sehingga total yang sudah disetujui tidak berubah.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $items = $data['items'] ?? null;
        unset($data['items']);

        $record->update($data);

        if ($record instanceof PurchaseOrder && $record->status === 'draft' && is_array($items)) {
            app(PurchaseOrderService::class)->ubahItem($record, $items);
        }

        return $record->fresh();
    }
}
