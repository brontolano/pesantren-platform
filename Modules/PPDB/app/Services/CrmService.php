<?php

namespace Modules\PPDB\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\SkemaBiayaService;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CrmAktivitas;
use Modules\PPDB\Models\CrmPenawaran;
use RuntimeException;

/**
 * G3-20 — CRM ringan: funnel pendaftaran, indikator churn (tunggakan + absensi),
 * campaign re-enrollment (idempoten), dan quotation dari SkemaBiaya (read-only).
 * TIDAK menghasilkan Tagihan/jurnal — murni baca + log.
 */
class CrmService
{
    /** Funnel pendaftaran: jumlah CalonSantri per status (opsional per tahun). */
    public function funnel(?string $tahun = null): array
    {
        $this->ensureTenantContext();

        return CalonSantri::query()
            ->when($tahun !== null, fn ($q) => $q->where('tahun_ajaran', $tahun))
            ->selectRaw('status_pendaftaran, count(*) as jml')
            ->groupBy('status_pendaftaran')
            ->pluck('jml', 'status_pendaftaran')
            ->map(fn ($v): int => (int) $v)
            ->all();
    }

    /**
     * Santri berisiko churn: tunggakan (sisa tagihan belum lunas) > ambang;
     * bila opts['rentang']=[dari,sampai] disertakan indikator kehadiran rendah.
     *
     * @return Collection<int, array{santri_id:int, nama:?string, tunggakan:float, kehadiran:?float, berisiko_absen:bool}>
     */
    public function churnBerisiko(array $opts = []): Collection
    {
        $this->ensureTenantContext();
        $ambangTunggakan = (float) ($opts['ambang_tunggakan'] ?? 0);
        $ambangHadir = (float) ($opts['ambang_hadir'] ?? 80);
        $rentang = $opts['rentang'] ?? null;

        // Eager-load pembayaran (cegah N+1 accessor) & batch nama santri (1 query).
        $perSantri = Tagihan::belumLunas()->with('pembayaran')->get()->groupBy('santri_id');
        $namaSantri = Santri::query()->whereIn('id', $perSantri->keys()->filter()->all())->pluck('nama', 'id');

        $hasil = collect();
        foreach ($perSantri as $santriId => $rows) {
            if ($santriId === null) {
                continue;
            }
            // Sisa per baris dihitung dari koleksi pembayaran termuat (tanpa query),
            // di-clamp >= 0 agar overpayment satu tagihan tak menetralkan tunggakan.
            $tunggakan = round($rows->sum(function (Tagihan $t): float {
                $dibayar = (float) $t->pembayaran->where('is_verified', true)->sum('nominal');

                return max(0.0, (float) $t->nominal_akhir - $dibayar);
            }), 2);

            $kehadiran = null;
            $berisikoAbsen = false;
            if (is_array($rentang) && count($rentang) === 2) {
                $kehadiran = $this->kehadiranPersen((int) $santriId, $rentang[0], $rentang[1]);
                $berisikoAbsen = $kehadiran !== null && $kehadiran < $ambangHadir;
            }

            // Berisiko bila tunggakan melebihi ambang ATAU kehadiran rendah.
            if ($tunggakan <= $ambangTunggakan && ! $berisikoAbsen) {
                continue;
            }

            $hasil->push([
                'santri_id' => (int) $santriId,
                'nama' => $namaSantri[$santriId] ?? null,
                'tunggakan' => $tunggakan,
                'kehadiran' => $kehadiran,
                'berisiko_absen' => $berisikoAbsen,
            ]);
        }

        return $hasil->sortByDesc('tunggakan')->values();
    }

    /**
     * Quotation dari SkemaBiaya (G2-16). Snapshot nominal tiap jenis → total.
     * READ-ONLY: tidak membuat Tagihan/jurnal.
     */
    public function buatPenawaran(array $data): CrmPenawaran
    {
        $this->ensureTenantContext();
        $tahun = $data['tahun_ajaran'] ?? null;
        if ($tahun === null) {
            throw new InvalidArgumentException('tahun_ajaran wajib diisi untuk penawaran.');
        }
        $jenisList = $data['jenis'] ?? [];
        if (! is_array($jenisList) || $jenisList === []) {
            throw new InvalidArgumentException('Daftar jenis biaya wajib diisi.');
        }
        $divisiId = $data['divisi_id'] ?? null;
        $skema = app(SkemaBiayaService::class);

        $items = [];
        $total = 0.0;
        foreach ($jenisList as $jenis) {
            $nominal = $skema->nominalUntuk($tahun, $divisiId, (string) $jenis);
            if ($nominal <= 0) {
                continue; // tak ada skema cocok → tak dimasukkan
            }
            $items[] = ['jenis' => $jenis, 'nominal' => round($nominal, 2)];
            $total += $nominal;
        }

        return CrmPenawaran::create([
            'calon_santri_id' => $data['calon_santri_id'] ?? null,
            'santri_id' => $data['santri_id'] ?? null,
            'nama_prospek' => $data['nama_prospek'] ?? null,
            'tahun_ajaran' => $tahun,
            'items' => $items,
            'total' => round($total, 2),
            'status' => 'draft',
            'keterangan' => $data['keterangan'] ?? null,
            'dibuat_oleh' => $data['dibuat_oleh'] ?? null,
        ]);
    }

    /**
     * Campaign re-enrollment: kirim reminder ke calon yang DITERIMA ('lulus')
     * tahun ini namun belum daftar ulang. IDEMPOTEN per (calon, ref) — tak ganda.
     *
     * @return int jumlah campaign baru terkirim
     */
    public function kirimCampaignDaftarUlang(string $tahun, array $opts = []): int
    {
        $this->ensureTenantContext();
        $ref = 'daftar_ulang:' . $tahun;

        $calon = CalonSantri::query()
            ->where('tahun_ajaran', $tahun)
            ->where('status_pendaftaran', 'lulus')
            ->whereNull('tanggal_registrasi_ulang')
            ->get();

        $n = 0;
        foreach ($calon as $c) {
            if (CrmAktivitas::where('calon_santri_id', $c->id)->where('ref', $ref)->exists()) {
                continue; // idempoten
            }
            try {
                CrmAktivitas::create([
                    'tipe' => 'campaign',
                    'calon_santri_id' => $c->id,
                    'kanal' => $opts['kanal'] ?? 'log',
                    'ref' => $ref,
                    'status' => 'terkirim',
                    'keterangan' => 'Reminder daftar ulang ' . $tahun,
                    'dikirim_pada' => now(),
                    'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
                ]);
                $this->kirimNotifBestEffort($c, $tahun);
                $n++;
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e; // race → sudah terkirim, abaikan
                }
            }
        }

        return $n;
    }

    private function kirimNotifBestEffort(CalonSantri $calon, string $tahun): void
    {
        try {
            app(\Modules\Notifikasi\Services\NotifDispatcher::class)
                ->kirim('crm.daftar_ulang', ['calon_santri_id' => $calon->id, 'tahun' => $tahun]);
        } catch (\Throwable) {
            // Best-effort: kegagalan kanal tak membatalkan log campaign.
        }
    }

    private function kehadiranPersen(int $santriId, string $dari, string $sampai): ?float
    {
        try {
            return app(\Modules\Akademik\Services\PresensiSantriService::class)
                ->rekap($santriId, $dari, $sampai)['persen'];
        } catch (\Throwable) {
            return null;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505';
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
