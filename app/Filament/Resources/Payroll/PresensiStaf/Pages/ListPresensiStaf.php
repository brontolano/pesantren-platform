<?php

namespace App\Filament\Resources\Payroll\PresensiStaf\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Payroll\PresensiStaf\PresensiStafResource;
use App\Models\Tenant;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Models\PresensiStaf;
use Modules\Payroll\Services\PresensiStafImportService;

class ListPresensiStaf extends ListRecords
{
    use HasImportAction;

    protected static string $resource = PresensiStafResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Opt-in: hanya muncul bila kebijakan 'import.presensi_staf_historis' aktif.
            ...$this->importActions([
                'entitas' => 'presensi_staf',
                'template' => ['nip', 'tanggal', 'status', 'jam_masuk', 'jam_keluar', 'keterangan'],
                'template_contoh' => ['198001012005011001', '2026-01-15', 'hadir', '07:00', '14:00', ''],
                'export' => fn (Tenant $t) => PresensiStaf::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('employee')->orderByDesc('tanggal')->lazy()
                    ->map(fn ($p): array => [
                        'nip' => $p->employee?->nip,
                        'tanggal' => optional($p->tanggal)->toDateString(),
                        'status' => $p->status,
                        'jam_masuk' => $p->jam_masuk,
                        'jam_keluar' => $p->jam_keluar,
                        'keterangan' => $p->keterangan,
                    ])
                    ->filter(fn ($r): bool => filled($r['nip'])),
                'label' => 'Import Presensi',
                'service' => PresensiStafImportService::class,
                'directory' => 'imports/payroll/presensi-staf/uploads',
                'ability' => 'run_payroll',
                'enabledSetting' => 'import.presensi_staf_historis',
                'petunjuk' => 'PERHATIAN: presensi staf memengaruhi poin Khidmah/Bisyaroh. Kolom: nip (wajib, pegawai harus sudah ada), tanggal (wajib), status (hadir/izin/sakit/alpa/cuti, default hadir), jam_masuk, jam_keluar, keterangan. Presensi (pegawai, tanggal) yang sudah ada dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
