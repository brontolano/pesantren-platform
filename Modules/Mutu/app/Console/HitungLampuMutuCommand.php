<?php

namespace Modules\Mutu\Console;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mutu\MutuService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Mutu\Models\MutuLampu;
use Modules\Notifikasi\Services\NotifDispatcher;

/**
 * 2a — Job harian Dasbor Mutu (pola CekKontrakHabisCommand):
 * hitung lampu kuning SEMUA santri aktif per tenant → refresh cache mutu_lampu
 * (upsert + hapus lampu yang sudah padam) → notifikasi mustahiq/wali sesuai
 * saklar Setting `mutu.notif` (default mustahiq ON, wali OFF).
 *
 * Idempoten:
 * - run 2× → baris mutu_lampu identik (upsert by unique tenant+santri+jenis,
 *   lampu padam dihapus);
 * - notifikasi maksimal 1×/hari per (santri, jenis) — guard last_notified_at
 *   di-stamp SEBELUM kirim (fire-and-forget, NotifDispatcher tak melempar).
 *
 * Per-tenant terisolasi: try/catch + hasTable — tenant belum migrasi tidak
 * menggugurkan tenant lain.
 */
class HitungLampuMutuCommand extends Command
{
    protected $signature = 'mutu:hitung-lampu
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Hitung lampu kuning Dasbor Mutu semua santri aktif + notifikasi mustahiq/wali';

    public function handle(): int
    {
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        $rows = [];
        foreach ($tenants as $tenant) {
            try {
                $hasil = $tenant->run(function (): array {
                    if (! Schema::hasTable('mutu_lampu') || ! Schema::hasTable('santri')) {
                        return ['santri' => 0, 'lampu' => 0, 'notif' => 0];
                    }

                    return $this->prosesTenant();
                });
                $rows[] = [$tenant->getKey(), $hasil['santri'], $hasil['lampu'], $hasil['notif']];
            } catch (\Throwable $e) {
                report($e);
                $rows[] = [$tenant->getKey(), 'GAGAL', $e->getMessage(), '-'];
            }
        }

        $this->table(['Tenant', 'Santri', 'Lampu', 'Notif'], $rows);

        return self::SUCCESS;
    }

    /** @return array{santri:int, lampu:int, notif:int} */
    private function prosesTenant(): array
    {
        $svc = app(MutuService::class);
        $saklar = $svc->notifSaklar();

        $jumlahSantri = 0;
        $jumlahLampu = 0;
        $jumlahNotif = 0;

        Santri::query()->where('status', 'aktif')->with('kelas')
            ->chunkById(200, function ($santris) use ($svc, $saklar, &$jumlahSantri, &$jumlahLampu, &$jumlahNotif): void {
                foreach ($santris as $santri) {
                    $jumlahSantri++;
                    $lampuList = $svc->lampu($santri);
                    $jenisAktif = [];

                    foreach ($lampuList as $lampu) {
                        $jenisAktif[] = $lampu['jenis'];
                        $row = MutuLampu::query()->updateOrCreate(
                            ['santri_id' => $santri->id, 'jenis' => $lampu['jenis']],
                            ['label' => $lampu['label'], 'detail' => $lampu['detail'], 'computed_at' => now()],
                        );
                        $jumlahLampu++;

                        $jumlahNotif += $this->notifikasi($row, $santri, $lampu, $saklar);
                    }

                    // Lampu yang sudah padam dihapus dari cache.
                    MutuLampu::query()
                        ->where('santri_id', $santri->id)
                        ->when($jenisAktif !== [], fn ($q) => $q->whereNotIn('jenis', $jenisAktif))
                        ->delete();
                }
            });

        return ['santri' => $jumlahSantri, 'lampu' => $jumlahLampu, 'notif' => $jumlahNotif];
    }

    /**
     * Kirim notifikasi SEKALI saat lampu menyala + pengingat mingguan bila
     * masih menyala (lampu 30 hari ≠ 30 WA; padam menghapus baris → siklus
     * baru menotifkan lagi).
     */
    private function notifikasi(MutuLampu $row, Santri $santri, array $lampu, array $saklar): int
    {
        if (! $saklar['mustahiq'] && ! $saklar['wali']) {
            return 0;
        }

        if ($row->last_notified_at !== null && $row->last_notified_at->gt(now()->subDays(7))) {
            return 0; // sudah dinotifkan dalam 7 hari terakhir — anti spam.
        }

        $tujuan = [];
        if ($saklar['mustahiq'] && $santri->kelas?->wali_kelas_id) {
            $mustahiq = User::withoutGlobalScopes()->find($santri->kelas->wali_kelas_id);
            if (filled($mustahiq?->phone)) {
                $tujuan[] = $mustahiq->phone;
            }
        }
        if ($saklar['wali'] && filled($santri->no_hp_wali)) {
            $tujuan[] = $santri->no_hp_wali;
        }

        if ($tujuan === []) {
            return 0;
        }

        // Stamp DULU — gagal kirim pun tak retry-spam hari ini; besok dicoba lagi.
        $row->forceFill(['last_notified_at' => today()])->save();

        app(NotifDispatcher::class)->kirim('mutu_lampu_kuning', [
            'wa' => array_values(array_unique($tujuan)),
            'data' => [
                'nama_santri' => $santri->nama,
                'nis' => $santri->nis ?? '-',
                'jenis' => $lampu['jenis'],
                'keterangan' => $lampu['label'],
                'nama_pesantren' => Setting::getTenantStringValue('branding_name')
                    ?? (tenant('name') ?? 'Pesantren'),
            ],
        ]);

        return 1; // 1 lampu dinotifkan (stamp sudah jalan, kirim fire-and-forget)
    }
}
