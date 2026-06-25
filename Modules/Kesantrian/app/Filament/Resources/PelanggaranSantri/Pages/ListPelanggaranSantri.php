<?php

namespace Modules\Kesantrian\Filament\Resources\PelanggaranSantri\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\PelanggaranSantriResource;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Services\PelanggaranImportService;

class ListPelanggaranSantri extends ListRecords
{
    use HasImportAction;

    protected static string $resource = PelanggaranSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Catat Kejadian'),
            // Opt-in: hanya muncul bila kebijakan 'import.pelanggaran_historis' aktif.
            ...$this->importActions([
                'entitas' => 'pelanggaran',
                'template' => ['nis', 'jenis', 'waktu_kejadian', 'kronologi', 'lokasi', 'status'],
                'template_contoh' => ['2024001', 'Terlambat jamaah subuh', '2026-01-15 05:30', 'Tidak ikut jamaah', 'Masjid', 'selesai'],
                'export' => fn (Tenant $t) => PelanggaranSantri::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['santri', 'jenis'])->orderByDesc('waktu_kejadian')->lazy()
                    ->map(fn ($p): array => [
                        'nis' => $p->santri?->nis,
                        'jenis' => $p->jenis?->nama,
                        'waktu_kejadian' => optional($p->waktu_kejadian)->toDateTimeString(),
                        'kronologi' => $p->kronologi,
                        'lokasi' => $p->lokasi,
                        'status' => $p->status,
                    ])
                    ->filter(fn ($r): bool => filled($r['nis']) && filled($r['jenis'])),
                'label' => 'Import Riwayat',
                'service' => PelanggaranImportService::class,
                // pencatat_id WAJIB → user pengimpor.
                'invoke' => fn ($service, Tenant $tenant, string $path, bool $dryRun, array $data): array => $service->import($tenant, $path, $dryRun, auth()->id()),
                'directory' => 'imports/kesantrian/pelanggaran/uploads',
                'ability' => 'manage_santri',
                'enabledSetting' => 'import.pelanggaran_historis',
                'petunjuk' => 'Impor DATA HISTORIS: TIDAK mengirim notifikasi wali & TIDAK menerbitkan SP otomatis. Kolom: nis (wajib), jenis (nama jenis pelanggaran, harus sudah ada), waktu_kejadian (wajib), kronologi, lokasi, status (default selesai). Baris (santri+jenis+waktu) yang sudah ada dilewati. Dry-run dulu.',
            ]),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'dicatat' => Tab::make('Menunggu Verifikasi')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'dicatat')),
            'diverifikasi' => Tab::make('Diverifikasi')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'diverifikasi')),
            'sanksi_dijalankan' => Tab::make('Sanksi Dijalankan')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'sanksi_dijalankan')),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'selesai')),
            'dibatalkan' => Tab::make('Dibatalkan')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'dibatalkan')),
        ];
    }
}
