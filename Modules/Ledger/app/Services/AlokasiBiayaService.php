<?php

namespace Modules\Ledger\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\AlokasiBiaya;
use RuntimeException;

/**
 * G3-23 — Cost allocation: distribusi beban bersama ke divisi via driver berbobot.
 * Posting REDISTRIBUSI dimensi divisi pada akun yang sama, LEWAT JurnalService
 * (math teruji tak diubah): D akun(divisi_i)=share_i ; K akun(pool)=nominal.
 * INVARIAN: Σ share = nominal (residu pembulatan dibebankan ke bobot terbesar)
 * → tak ada uang hilang/tercipta; jurnal selalu seimbang.
 */
class AlokasiBiayaService
{
    public function __construct(private readonly JurnalService $jurnalService) {}

    /**
     * @param  array<int,float>|array<int,array{divisi_id:int,bobot:float}>  $driver
     *                                                                                Bentuk [divisi_id => bobot] ATAU [['divisi_id'=>..,'bobot'=>..], ...].
     * @param  array{periode?:string, keterangan?:string, tanggal?:string, dibuat_oleh?:int}  $opts
     */
    public function alokasikan(Akun|int $akun, float $nominal, array $driver, array $opts = []): AlokasiBiaya
    {
        $this->ensureTenantContext();
        $akun = $akun instanceof Akun ? $akun : Akun::query()->findOrFail($akun);
        $nominal = round($nominal, 2);
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal alokasi harus lebih dari 0.');
        }

        $rows = $this->normalisasiDriver($driver);
        $totalBobot = array_sum(array_column($rows, 'bobot'));
        if ($totalBobot <= 0) {
            throw new InvalidArgumentException('Total bobot driver harus lebih dari 0.');
        }

        $shares = $this->hitungShare($rows, $nominal, $totalBobot);

        return DB::transaction(function () use ($akun, $nominal, $rows, $shares, $opts): AlokasiBiaya {
            $alokasi = AlokasiBiaya::create([
                'akun_id' => $akun->id,
                'nominal' => $nominal,
                'driver' => $rows,
                'periode' => $opts['periode'] ?? null,
                'keterangan' => $opts['keterangan'] ?? null,
                'status' => 'draft',
                'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
            ]);

            $baris = [];
            foreach ($shares as $s) {
                if ($s['share'] <= 0) {
                    continue; // divisi tanpa porsi tak dijurnal
                }
                $baris[] = ['akun_id' => $akun->id, 'divisi_id' => $s['divisi_id'], 'debit' => $s['share'], 'kredit' => 0, 'memo' => 'Alokasi beban ke divisi'];
            }
            // Kredit pool (divisi null) sebesar total → seimbang dengan Σ share.
            $baris[] = ['akun_id' => $akun->id, 'divisi_id' => null, 'debit' => 0, 'kredit' => $nominal, 'memo' => 'Pool beban dialokasikan'];

            $jurnal = $this->jurnalService->postJurnal([
                'tanggal' => isset($opts['tanggal']) ? Carbon::parse($opts['tanggal']) : Carbon::now(),
                'deskripsi' => 'Alokasi biaya — '.$akun->nama.(! empty($opts['periode']) ? ' ('.$opts['periode'].')' : ''),
                'tipe_sumber' => 'alokasi_biaya',
                'ref_type' => 'AlokasiBiaya',
                'ref_id' => (string) $alokasi->id,
                'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
                'baris' => $baris,
            ]);

            $alokasi->update(['status' => 'posted', 'jurnal_id' => $jurnal->id]);

            return $alokasi->fresh();
        });
    }

    /** @return array<int,array{divisi_id:int,bobot:float}> */
    private function normalisasiDriver(array $driver): array
    {
        $rows = [];
        foreach ($driver as $key => $val) {
            if (is_array($val)) {
                $rows[] = ['divisi_id' => (int) $val['divisi_id'], 'bobot' => (float) $val['bobot']];
            } else {
                $rows[] = ['divisi_id' => (int) $key, 'bobot' => (float) $val];
            }
        }
        if ($rows === []) {
            throw new InvalidArgumentException('Driver alokasi wajib diisi.');
        }
        foreach ($rows as $r) {
            if ($r['bobot'] < 0) {
                throw new InvalidArgumentException('Bobot driver tidak boleh negatif.');
            }
        }

        return $rows;
    }

    /**
     * Share proporsional; residu pembulatan dibebankan ke divisi BOBOT TERBESAR
     * → Σ share = nominal PERSIS.
     *
     * @param  array<int,array{divisi_id:int,bobot:float}>  $rows
     * @return array<int,array{divisi_id:int,share:float}>
     */
    private function hitungShare(array $rows, float $nominal, float $totalBobot): array
    {
        $shares = [];
        $sum = 0.0;
        $maxIdx = 0;
        foreach ($rows as $i => $r) {
            $share = round($nominal * $r['bobot'] / $totalBobot, 2);
            $shares[$i] = ['divisi_id' => $r['divisi_id'], 'share' => $share];
            $sum = round($sum + $share, 2);
            if ($r['bobot'] > $rows[$maxIdx]['bobot']) {
                $maxIdx = $i;
            }
        }
        $residu = round($nominal - $sum, 2);
        if (abs($residu) >= 0.005) {
            $shares[$maxIdx]['share'] = round($shares[$maxIdx]['share'] + $residu, 2);
        }

        return array_values($shares);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
