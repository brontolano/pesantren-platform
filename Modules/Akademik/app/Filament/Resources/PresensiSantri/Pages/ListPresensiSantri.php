<?php

namespace Modules\Akademik\Filament\Resources\PresensiSantri\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\PresensiSantri\PresensiSantriResource;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Akademik\Services\PresensiSantriImportService;

class ListPresensiSantri extends ListRecords
{
    use HasImportAction;

    protected static string $resource = PresensiSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Catat Presensi'),
            ...$this->importActions([
                'entitas' => 'presensi',
                'template' => ['nis', 'tanggal', 'status', 'kelas', 'keterangan'],
                'template_contoh' => ['2024001', '2026-01-15', 'hadir', '1 Ibtidaiyah', ''],
                'export' => fn (Tenant $t) => PresensiSantri::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['santri', 'kelas'])->orderByDesc('tanggal')->lazy()
                    ->map(fn ($p): array => [
                        'nis' => $p->santri?->nis,
                        'tanggal' => optional($p->tanggal)->toDateString(),
                        'status' => $p->status,
                        'kelas' => $p->kelas?->nama_kelas,
                        'keterangan' => $p->keterangan,
                    ])
                    ->filter(fn ($r): bool => filled($r['nis'])),
                'label' => 'Import Presensi',
                'service' => PresensiSantriImportService::class,
                'directory' => 'imports/akademik/presensi/uploads',
                'ability' => 'manage_akademik',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis (wajib), tanggal (wajib), status (hadir/izin/sakit/alpa, default hadir), kelas (opsional, default kelas santri), keterangan. Presensi (santri, tanggal) yang sudah ada dilewati (tak ditimpa). Dry-run dulu.',
            ]),
        ];
    }
}
