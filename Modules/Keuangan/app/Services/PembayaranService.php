<?php

namespace Modules\Keuangan\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Events\PembayaranDiverifikasi;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;

class PembayaranService
{
    /**
     * Alokasikan SATU pembayaran ke banyak tagihan (pola Payment Entry ERPNext).
     *
     * Wali bayar borongan (mis. 3 bulan SPP sekaligus) → dana dialokasi FIFO
     * berdasarkan jatuh tempo (atau urutan `tagihan_ids` bila diberikan). Sisa
     * lebih (uang muka) dititipkan ke Tabungan santri sebagai saldo advance.
     *
     * @param  array{created_by?:int, auto_verify?:bool, metode?:string, catatan?:string, tagihan_ids?:int[], titipan_ke_tabungan?:bool}  $opts
     * @return array{total:float, teralokasi:float, alokasi:array<int,array{tagihan_id:int,nominal:float}>, titipan:float}
     */
    public function alokasiPembayaran(Santri|int $santri, float $totalNominal, array $opts = []): array
    {
        $this->ensureTenantContext();

        $total = round($totalNominal, 2);
        if ($total <= 0) {
            throw new InvalidArgumentException('Nominal alokasi pembayaran harus lebih dari 0.');
        }

        $santriId = $santri instanceof Santri ? (int) $santri->id : (int) $santri;
        $userId = $opts['created_by'] ?? null;
        $autoVerify = $opts['auto_verify'] ?? true;

        $query = Tagihan::query()->where('santri_id', $santriId)->belumLunas();
        if (! empty($opts['tagihan_ids'])) {
            $ids = array_values($opts['tagihan_ids']);
            $tagihanList = $query->whereIn('id', $ids)->get()
                ->sortBy(fn (Tagihan $t): int => array_search($t->id, $ids, true))->values();
        } else {
            $tagihanList = $query->orderBy('jatuh_tempo')->orderBy('id')->get();
        }

        return DB::transaction(function () use ($tagihanList, $total, $opts, $userId, $autoVerify, $santriId): array {
            $sisa = $total;
            $alokasi = [];

            foreach ($tagihanList as $tagihan) {
                if ($sisa < 0.005) {
                    break;
                }
                $due = round((float) $tagihan->sisa_tagihan, 2);
                if ($due <= 0) {
                    continue;
                }
                $bayar = round(min($due, $sisa), 2);

                $pembayaran = $this->inputPembayaran($tagihan, [
                    'nominal' => $bayar,
                    'metode' => $opts['metode'] ?? 'tunai',
                    'created_by' => $userId,
                    'catatan' => $opts['catatan'] ?? 'Alokasi pembayaran borongan',
                ]);
                if ($autoVerify && $userId) {
                    $this->verifikasiPembayaran($pembayaran, (int) $userId);
                }

                $alokasi[] = ['tagihan_id' => (int) $tagihan->id, 'nominal' => $bayar];
                $sisa = round($sisa - $bayar, 2);
            }

            // Sisa lebih → titipan/uang muka ke Tabungan santri (saldo advance).
            $titipan = 0.0;
            if ($sisa >= 0.005) {
                $titipan = $sisa;
                if ($opts['titipan_ke_tabungan'] ?? true) {
                    app(TabunganService::class)->setor($santriId, $titipan, [
                        'keterangan' => 'Titipan/uang muka dari kelebihan pembayaran tagihan',
                        'created_by' => $userId,
                    ]);
                }
            }

            return [
                'total' => $total,
                'teralokasi' => round(array_sum(array_column($alokasi, 'nominal')), 2),
                'alokasi' => $alokasi,
                'titipan' => round($titipan, 2),
            ];
        });
    }

    /**
     * Input pembayaran manual oleh bendahara (T1.6.4).
     * Mendukung partial payment / cicilan (T1.6.5).
     */
    public function inputPembayaran(Tagihan|int $tagihan, array $payload): Pembayaran
    {
        $this->ensureTenantContext();

        $tagihanModel = $tagihan instanceof Tagihan
            ? $tagihan
            : Tagihan::query()->findOrFail($tagihan);

        if ($tagihanModel->status === 'lunas') {
            throw new InvalidArgumentException('Tagihan sudah lunas, tidak bisa menerima pembayaran.');
        }
        if ($tagihanModel->status === 'batal') {
            throw new InvalidArgumentException('Tagihan sudah dibatalkan.');
        }

        $nominal = (float) ($payload['nominal'] ?? 0);
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
        }

        $sisaTagihan = $tagihanModel->sisa_tagihan;
        if ($nominal > $sisaTagihan && $sisaTagihan > 0) {
            throw new InvalidArgumentException(
                "Nominal pembayaran ({$nominal}) melebihi sisa tagihan ({$sisaTagihan})."
            );
        }

        return DB::transaction(function () use ($tagihanModel, $payload, $nominal): Pembayaran {
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihanModel->id,
                'santri_id' => $tagihanModel->santri_id,
                'nominal' => $nominal,
                'metode' => $payload['metode'] ?? 'tunai',
                'tanggal_pembayaran' => $payload['tanggal_pembayaran'] ?? now()->toDateString(),
                'nomor_bukti' => $payload['nomor_bukti'] ?? null,
                'bukti_pembayaran' => $payload['bukti_pembayaran'] ?? null,
                'catatan' => $payload['catatan'] ?? null,
                'is_verified' => false,
                'created_by' => $payload['created_by'] ?? null,
            ]);

            $this->updateStatusTagihan($tagihanModel);

            return $pembayaran;
        });
    }

    /**
     * Verifikasi pembayaran oleh admin/bendahara.
     */
    public function verifikasiPembayaran(Pembayaran|int $pembayaran, int $verifiedBy): Pembayaran
    {
        $this->ensureTenantContext();

        $pembayaranId = $pembayaran instanceof Pembayaran ? $pembayaran->id : $pembayaran;

        // RACE GUARD: gerbang is_verified + cek sisa tagihan WAJIB pada baris
        // ter-LOCK dalam SATU transaksi. Tanpa ini: (a) dua admin memverifikasi
        // pembayaran yang sama bersamaan → dua-duanya lolos (double-verify);
        // (b) dua bukti bayar bernilai penuh diverifikasi semua → total verified
        // MELEBIHI tagihan (overshoot lintas-jalur dgn BayarDariSaldoService).
        $model = DB::transaction(function () use ($pembayaranId, $verifiedBy): Pembayaran {
            $locked = Pembayaran::query()->whereKey($pembayaranId)->lockForUpdate()->firstOrFail();

            if ($locked->is_verified) {
                throw new InvalidArgumentException('Pembayaran sudah diverifikasi.');
            }

            // Lock tagihan (urutan konsisten dgn BayarDariSaldo: Tagihan → lain-lain);
            // sisa dihitung dari pembayaran verified ter-commit SETELAH lock.
            $tagihan = Tagihan::query()->whereKey($locked->tagihan_id)->lockForUpdate()->firstOrFail();
            $sisa = $tagihan->sisa_tagihan;
            if ((float) $locked->nominal > $sisa + 0.001) {
                throw new InvalidArgumentException(
                    "Nominal pembayaran melebihi sisa tagihan ({$sisa}). Tolak/koreksi pembayaran ini."
                );
            }

            // G1-01: pagar persetujuan (F1). DEFAULT no-op (tak ada ApprovalRule wajib
            // 'Pembayaran' ter-seed) → alur uang LIVE tidak terblokir. Aktif hanya bila
            // tenant sengaja membuat rule (mis. nominal besar).
            $locked->pastikanBolehLanjut();

            $locked->update([
                'is_verified' => true,
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
            ]);

            // Re-evaluate status tagihan setelah verifikasi
            $this->updateStatusTagihan($tagihan->fresh());

            return $locked;
        });

        // Pancarkan event auto-jurnal (didengarkan modul Ledger bila aktif).
        // Di luar transaksi (perilaku semula); jurnal idempoten by ref Pembayaran:id.
        PembayaranDiverifikasi::dispatch($model->fresh(), $verifiedBy);

        // Notif WA ke wali (TGH-5) — fire-and-forget SETELAH commit, tak pernah throw.
        app(KeuanganNotifikasiService::class)->pembayaranDiverifikasi($model->fresh());

        return $model->fresh();
    }

    /**
     * Tolak pembayaran wali (set alasan + verifier, lalu recalculate status tagihan).
     */
    public function tolakPembayaran(Pembayaran|int $pembayaran, int $verifiedBy, string $alasan): Pembayaran
    {
        $this->ensureTenantContext();

        $pembayaranId = $pembayaran instanceof Pembayaran ? $pembayaran->id : $pembayaran;

        // Lock + cek is_verified dalam transaksi — cegah tolak menimpa pembayaran
        // yang baru saja diverifikasi proses lain (jurnal sudah terposting).
        $model = DB::transaction(function () use ($pembayaranId, $verifiedBy, $alasan): Pembayaran {
            $locked = Pembayaran::query()->whereKey($pembayaranId)->lockForUpdate()->firstOrFail();

            if ($locked->is_verified) {
                throw new InvalidArgumentException('Pembayaran sudah diverifikasi, tidak bisa ditolak.');
            }

            $locked->update([
                'alasan_penolakan' => $alasan,
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
            ]);

            $this->updateStatusTagihan($locked->tagihan);

            return $locked->fresh();
        });

        // Notif WA ke wali (TGH-5) — fire-and-forget SETELAH commit.
        app(KeuanganNotifikasiService::class)->pembayaranDitolak($model, $alasan);

        return $model;
    }

    /**
     * Batalkan pembayaran (soft — hanya hapus record, tagihan di-recalculate).
     */
    public function batalkanPembayaran(Pembayaran|int $pembayaran): void
    {
        $this->ensureTenantContext();

        $pembayaranId = $pembayaran instanceof Pembayaran ? $pembayaran->id : $pembayaran;

        // Lock + guard is_verified dalam transaksi — pembatalan tidak boleh
        // balapan dengan verifikasi: pembayaran terverifikasi sudah terposting
        // ke jurnal Ledger; menghapusnya membuat buku besar tak sinkron.
        DB::transaction(function () use ($pembayaranId): void {
            $locked = Pembayaran::query()->whereKey($pembayaranId)->lockForUpdate()->firstOrFail();

            if ($locked->is_verified) {
                throw new InvalidArgumentException(
                    'Pembayaran sudah diverifikasi, batalkan lewat mekanisme koreksi jurnal.'
                );
            }

            $tagihan = $locked->tagihan;
            $locked->delete();
            $this->updateStatusTagihan($tagihan->fresh());
        });
    }

    /**
     * Recalculate status tagihan berdasarkan total pembayaran terverifikasi.
     */
    public function updateStatusTagihan(Tagihan $tagihan): void
    {
        $totalDibayar = $tagihan->pembayaran()
            ->where('is_verified', true)
            ->sum('nominal');

        $nominalAkhir = (float) $tagihan->nominal_akhir;

        if ($totalDibayar >= $nominalAkhir && $nominalAkhir > 0) {
            $tagihan->update(['status' => 'lunas']);
        } elseif ($totalDibayar > 0) {
            $tagihan->update(['status' => 'sebagian']);
        } else {
            $tagihan->update(['status' => 'belum_bayar']);
        }
    }

    /**
     * List pembayaran untuk satu tagihan.
     */
    public function listPembayaranTagihan(Tagihan|int $tagihan): Collection
    {
        $this->ensureTenantContext();

        $id = $tagihan instanceof Tagihan ? $tagihan->id : $tagihan;

        return Pembayaran::query()
            ->where('tagihan_id', $id)
            ->orderBy('tanggal_pembayaran', 'desc')
            ->get();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
