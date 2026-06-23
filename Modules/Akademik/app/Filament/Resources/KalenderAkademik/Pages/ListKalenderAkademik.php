<?php

namespace Modules\Akademik\Filament\Resources\KalenderAkademik\Pages;

use App\Filament\Concerns\HasImportAction;
use Modules\Akademik\Filament\Resources\KalenderAkademik\KalenderAkademikResource;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Models\KalenderAkademik;
use Modules\Akademik\Services\KalenderImportService;

class ListKalenderAkademik extends ListRecords
{
    use HasImportAction;

    protected static string $resource = KalenderAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Entri'),
            // Opt-in: hanya muncul bila kebijakan 'import.kalender_kustom' aktif.
            ...$this->importActions([
                'entitas' => 'kalender',
                'template' => ['tanggal_mulai', 'tanggal_selesai', 'jenis', 'keterangan', 'lembaga'],
                'template_contoh' => ['2026-07-01', '2026-07-14', 'libur_pondok', 'Libur akhir tahun ajaran', ''],
                'export' => fn (Tenant $t) => KalenderAkademik::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('lembaga')->orderBy('tanggal_mulai')->lazy()
                    ->map(fn ($k): array => [
                        'tanggal_mulai' => optional($k->tanggal_mulai)->toDateString(),
                        'tanggal_selesai' => optional($k->tanggal_selesai)->toDateString(),
                        'jenis' => $k->jenis,
                        'keterangan' => $k->keterangan,
                        'lembaga' => $k->lembaga?->nama,
                    ]),
                'label' => 'Import Kalender',
                'service' => KalenderImportService::class,
                'directory' => 'imports/akademik/kalender/uploads',
                'ability' => 'manage_akademik',
                'enabledSetting' => 'import.kalender_kustom',
                'petunjuk' => 'Untuk acara/libur khusus pesantren (libur nasional sudah otomatis). Kolom: tanggal_mulai (wajib), tanggal_selesai (kosong = sama dgn mulai), jenis (libur_nasional/libur_pondok/ramadhan/ujian/pasaran/kegiatan, default libur_pondok), keterangan (wajib), lembaga (kosong = semua). Entri (tanggal_mulai+keterangan) yang sudah ada dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
