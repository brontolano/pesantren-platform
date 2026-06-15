<?php

namespace App\Filament\Resources\Kepengasuhan\PresensiAsrama\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Kepengasuhan\PresensiAsrama\PresensiAsramaResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Services\PresensiAsramaImportService;

class ListPresensiAsrama extends ListRecords
{
    use HasImportAction;

    protected static string $resource = PresensiAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('isi_presensi')
                ->label('Isi Presensi')
                ->icon('heroicon-o-pencil-square')
                ->url(PresensiAsramaResource::getUrl('isi')),
            Action::make('rekap')
                ->label('Rekap')
                ->icon('heroicon-o-chart-bar')
                ->url(PresensiAsramaResource::getUrl('rekap'))
                ->color('gray'),
            ...$this->importActions([
                'entitas' => 'presensi_asrama',
                'template' => ['nis', 'tanggal', 'slot', 'status', 'keterangan'],
                'template_contoh' => ['2024001', '2026-01-15', 'subuh', 'hadir', ''],
                'export' => fn (Tenant $t) => PresensiAsrama::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('santri')->orderByDesc('tanggal')->lazy()
                    ->map(fn ($p): array => [
                        'nis' => $p->santri?->nis,
                        'tanggal' => optional($p->tanggal)->toDateString(),
                        'slot' => $p->slot,
                        'status' => $p->status,
                        'keterangan' => $p->keterangan,
                    ])
                    ->filter(fn ($r): bool => filled($r['nis'])),
                'label' => 'Import Presensi',
                'service' => PresensiAsramaImportService::class,
                // pencatat_id WAJIB → user pengimpor.
                'invoke' => fn ($service, Tenant $tenant, string $path, bool $dryRun, array $data): array => $service->import($tenant, $path, $dryRun, auth()->id()),
                'directory' => 'imports/kesantrian/presensi-asrama/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis (wajib), tanggal (wajib), slot (wajib, mis. subuh/maghrib/isya), status (hadir/alpha/izin/sakit/terlambat, default hadir), keterangan. Presensi (santri, tanggal, slot) yang sudah ada dilewati (tak ditimpa). Dry-run dulu.',
            ]),
        ];
    }
}
