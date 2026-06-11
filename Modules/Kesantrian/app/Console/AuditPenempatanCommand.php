<?php

namespace Modules\Kesantrian\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-05 — Audit drift Santri.kelas_id vs penempatan aktif.
 *
 * Default: HANYA melaporkan (read-only). Perbaikan data hanya dengan
 * flag --fix eksplisit (kelas_id diset mengikuti penempatan aktif).
 *
 * Santri TANPA penempatan aktif sengaja dilewati: data lama yang sah
 * mengisi kelas_id langsung tanpa baris penempatan (impor/seed awal).
 */
class AuditPenempatanCommand extends Command
{
    protected $signature = 'kesantrian:audit-penempatan
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}
        {--fix : Perbaiki kelas_id santri mengikuti penempatan aktifnya}';

    protected $description = 'Laporkan santri yang kelas_id-nya tidak sinkron dengan penempatan kelas aktif';

    public function handle(): int
    {
        $target = $this->argument('tenant');
        $fix = (bool) $this->option('fix');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error('Tenant tidak ditemukan.');

            return self::FAILURE;
        }

        $totalDrift = 0;

        foreach ($tenants as $tenant) {
            $rows = $tenant->run(fn (): array => $this->auditTenant($fix));

            if ($rows === []) {
                $this->info("[{$tenant->getKey()}] Sinkron — tidak ada drift.");
                continue;
            }

            $totalDrift += count($rows);
            $this->warn("[{$tenant->getKey()}] Drift ditemukan: ".count($rows).' santri'.($fix ? ' (DIPERBAIKI)' : ''));
            $this->table(
                ['Santri ID', 'Nama', 'kelas_id Santri', 'Kelas Penempatan Aktif'],
                $rows,
            );
        }

        if ($totalDrift > 0 && ! $fix) {
            $this->line('Jalankan ulang dengan --fix untuk menyetel kelas_id mengikuti penempatan aktif.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0:int,1:string,2:string,3:string}>
     */
    private function auditTenant(bool $fix): array
    {
        // Penempatan aktif TERBARU per santri (toleran terhadap data lama
        // yang sempat punya >1 baris is_current).
        $aktifPerSantri = PenempatanKelas::query()
            ->where('is_current', true)
            ->with('kelas')
            ->orderByDesc('tanggal_penempatan')
            ->orderByDesc('id')
            ->get()
            ->groupBy('santri_id')
            ->map->first();

        if ($aktifPerSantri->isEmpty()) {
            return [];
        }

        $santriMap = Santri::query()
            ->whereIn('id', $aktifPerSantri->keys())
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($aktifPerSantri as $santriId => $penempatan) {
            $santri = $santriMap->get($santriId);

            if (! $santri || (int) $santri->kelas_id === (int) $penempatan->kelas_id) {
                continue;
            }

            $rows[] = [
                $santri->id,
                $santri->nama,
                (string) ($santri->kelas_id ?? '—'),
                sprintf('%s (id %d)', $penempatan->kelas?->nama_kelas ?? '?', $penempatan->kelas_id),
            ];

            if ($fix) {
                // Query builder: tanpa event model, tanpa efek samping lain.
                Santri::query()
                    ->whereKey($santri->id)
                    ->update(['kelas_id' => $penempatan->kelas_id]);
            }
        }

        return $rows;
    }
}
