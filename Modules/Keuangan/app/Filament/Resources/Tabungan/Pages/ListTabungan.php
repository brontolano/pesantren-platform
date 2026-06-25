<?php

namespace Modules\Keuangan\Filament\Resources\Tabungan\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\Tabungan\TabunganResource;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Services\TabunganSaldoImportService;

class ListTabungan extends ListRecords
{
    use HasImportAction;

    protected static string $resource = TabunganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->importActions([
                'entitas' => 'saldo tabungan',
                'template' => ['nis', 'saldo_awal', 'keterangan'],
                'template_contoh' => ['2024001', '150000', 'Saldo migrasi'],
                'export' => fn (Tenant $t) => Tabungan::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('santri')->lazy()
                    ->map(fn ($tb): array => ['nis' => $tb->santri?->nis, 'saldo_awal' => $tb->saldo, 'keterangan' => '']),
                'label' => 'Import Saldo Awal',
                'service' => TabunganSaldoImportService::class,
                'directory' => 'imports/keuangan/tabungan/uploads',
                'ability' => 'manage_keuangan',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis (wajib), saldo_awal (wajib, angka), keterangan. Membuat transaksi pembuka tabungan. Idempoten: santri yang sudah punya saldo awal dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
