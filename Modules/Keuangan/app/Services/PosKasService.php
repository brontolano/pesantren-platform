<?php

namespace Modules\Keuangan\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\PosKategori;
use Modules\Keuangan\Models\PosSesi;
use Modules\Keuangan\Models\PosTransaksi;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\JurnalService;
use RuntimeException;

/**
 * Layanan POS Kas — sesi kasir + akuntabilitas tunai.
 *
 * Lapis ini menangani: buka/tutup sesi, rekonsiliasi selisih, dan PEREKAMAN
 * transaksi tunai (catatManual). Routing ke service lain (Tabungan/Pembayaran)
 * + posting Ledger ditangani method `catat()` (POS-4) yang memanggil perekaman
 * di sini sehingga saldo sesi tetap satu sumber kebenaran.
 *
 * Invarian:
 *   saldo_sistem = saldo_awal + Σ pemasukan − Σ pengeluaran
 *   selisih      = saldo_fisik − saldo_sistem   (dihitung saat tutup)
 * State-machine: transaksi/tutup hanya pada sesi berstatus 'buka'.
 */
class PosKasService
{
    public function bukaSesi(array $data = []): PosSesi
    {
        $this->ensureTenantContext();

        $saldoAwal = round((float) ($data['saldo_awal'] ?? 0), 2);
        if ($saldoAwal < 0) {
            throw new InvalidArgumentException('Saldo awal tidak boleh negatif.');
        }

        return PosSesi::create([
            'kasir_id' => $data['kasir_id'] ?? null,
            'pos_jenis' => $data['pos_jenis'] ?? 'kantor_keuangan',
            'saldo_awal' => $saldoAwal,
            'saldo_sistem' => $saldoAwal,
            'status' => 'buka',
            'dibuka_pada' => now(),
            'catatan' => $data['catatan'] ?? null,
        ]);
    }

    /**
     * Rekam transaksi tunai tanpa integrasi service lain (manual / counter-account
     * sudah ditangani pemanggil). Menjaga saldo_sistem sesi.
     */
    public function catatManual(PosSesi|int $sesi, PosKategori|int $kategori, float $nominal, array $opts = []): PosTransaksi
    {
        $this->ensureTenantContext();

        $sesi = $this->resolveSesi($sesi);
        $this->ensureBuka($sesi);
        $kat = $this->resolveKategori($kategori);

        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal transaksi harus lebih dari 0.');
        }

        return DB::transaction(function () use ($sesi, $kat, $nominal, $opts): PosTransaksi {
            $tx = PosTransaksi::create([
                'sesi_id' => $sesi->id,
                'kategori_id' => $kat->id,
                'jenis' => $kat->jenis,
                'nominal' => round($nominal, 2),
                'santri_id' => $opts['santri_id'] ?? null,
                'ref_type' => $opts['ref_type'] ?? null,
                'ref_id' => $opts['ref_id'] ?? null,
                'jurnal_id' => $opts['jurnal_id'] ?? null,
                'keterangan' => $opts['keterangan'] ?? null,
                'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
            ]);

            $this->recomputeSaldoSistem($sesi);

            return $tx;
        });
    }

    /**
     * Catat transaksi POS lengkap: ROUTING per kategori.handler → rekam tunai.
     *
     * Handler delegasi (tabungan/pembayaran) memposting Ledger sendiri lewat
     * service masing-masing (TIDAK diduplikasi di sini). Handler direct
     * (pengeluaran/koperasi/infaq/umum) diposting via postingLangsung().
     *
     * @param  array{nominal:float|int, santri_id?:int, tagihan_id?:int, keterangan?:string, dibuat_oleh?:int}  $payload
     */
    public function catat(PosSesi|int $sesi, PosKategori|int $kategori, array $payload = []): PosTransaksi
    {
        $this->ensureTenantContext();

        $sesi = $this->resolveSesi($sesi);
        $this->ensureBuka($sesi);
        $kat = $this->resolveKategori($kategori);

        $nominal = round((float) ($payload['nominal'] ?? 0), 2);
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal transaksi harus lebih dari 0.');
        }

        $userId = $payload['dibuat_oleh'] ?? $sesi->kasir_id;

        return DB::transaction(function () use ($sesi, $kat, $nominal, $payload, $userId): PosTransaksi {
            $refType = null;
            $refId = null;

            // (1) Handler DELEGASI — service terkait memposting Ledger sendiri.
            switch ($kat->handler) {
                case 'tabungan_setor':
                    $trx = app(TabunganService::class)->setor($this->santriId($payload), $nominal, [
                        'keterangan' => $payload['keterangan'] ?? 'Setor tabungan via POS',
                        'created_by' => $userId,
                    ]);
                    $refType = 'TabunganTransaksi';
                    $refId = (string) $trx->id;
                    break;

                case 'tabungan_tarik':
                    $trx = app(TabunganService::class)->tarik($this->santriId($payload), $nominal, [
                        'keterangan' => $payload['keterangan'] ?? 'Tarik tabungan via POS',
                        'created_by' => $userId,
                    ]);
                    $refType = 'TabunganTransaksi';
                    $refId = (string) $trx->id;
                    break;

                case 'daftar_ulang':
                case 'spp':
                    if (empty($payload['tagihan_id'])) {
                        throw new InvalidArgumentException("Handler '{$kat->handler}' membutuhkan 'tagihan_id' pada payload.");
                    }
                    $bayar = app(PembayaranService::class)->inputPembayaran((int) $payload['tagihan_id'], [
                        'nominal' => $nominal,
                        'metode' => 'tunai',
                        'created_by' => $userId,
                    ]);
                    app(PembayaranService::class)->verifikasiPembayaran($bayar, (int) $userId);
                    $refType = 'Pembayaran';
                    $refId = (string) $bayar->id;
                    break;
            }

            // (2) Rekam transaksi tunai → saldo sesi tetap satu sumber kebenaran.
            $tx = $this->catatManual($sesi, $kat, $nominal, [
                'santri_id' => $payload['santri_id'] ?? null,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'keterangan' => $payload['keterangan'] ?? $kat->nama,
                'dibuat_oleh' => $userId,
            ]);

            // (3) Handler DIRECT — posting Ledger langsung (bila COA & akun siap).
            if (in_array($kat->handler, ['pengeluaran', 'koperasi', 'infaq', 'umum'], true)) {
                $jurnal = $this->postingLangsung($kat, $nominal, $tx, $userId);
                if ($jurnal !== null) {
                    $tx->update(['jurnal_id' => $jurnal->id]);
                }
            }

            return $tx->fresh();
        });
    }

    /**
     * Posting Ledger langsung untuk transaksi tunai non-delegasi.
     * Pemasukan: Debit Kas, Kredit akun lawan (pendapatan).
     * Pengeluaran: Debit akun lawan (beban), Kredit Kas.
     * Idempoten via (ref_type=PosTransaksi, ref_id). Mengembalikan null bila
     * COA belum siap / akun lawan belum dikonfigurasi (menahan diri, tak gagal).
     */
    private function postingLangsung(PosKategori $kat, float $nominal, PosTransaksi $tx, ?int $userId): ?Jurnal
    {
        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        $counter = $kat->akun_id ? Akun::query()->find($kat->akun_id) : null;

        if ($kas === null || $counter === null) {
            return null;
        }

        $memo = 'POS '.$kat->nama;

        $baris = $kat->jenis === 'pemasukan'
            ? [
                ['akun_id' => $kas->id, 'debit' => $nominal, 'kredit' => 0, 'memo' => 'Kas masuk — '.$memo],
                ['akun_id' => $counter->id, 'sumber_dana_id' => $kat->sumber_dana_id, 'debit' => 0, 'kredit' => $nominal, 'memo' => $memo],
            ]
            : [
                ['akun_id' => $counter->id, 'sumber_dana_id' => $kat->sumber_dana_id, 'debit' => $nominal, 'kredit' => 0, 'memo' => $memo],
                ['akun_id' => $kas->id, 'debit' => 0, 'kredit' => $nominal, 'memo' => 'Kas keluar — '.$memo],
            ];

        return app(JurnalService::class)->postJurnal([
            'tanggal' => now(),
            'deskripsi' => $memo.' #'.$tx->id,
            'tipe_sumber' => 'pos',
            'ref_type' => 'PosTransaksi',
            'ref_id' => (string) $tx->id,
            'dibuat_oleh' => $userId,
            'baris' => $baris,
        ]);
    }

    private function santriId(array $payload): int
    {
        $id = $payload['santri_id'] ?? null;
        if (empty($id)) {
            throw new InvalidArgumentException('Handler tabungan membutuhkan santri_id pada payload.');
        }

        return (int) $id;
    }

    public function tutupSesi(PosSesi|int $sesi, float $saldoFisik): PosSesi
    {
        $this->ensureTenantContext();

        $sesi = $this->resolveSesi($sesi);
        $this->ensureBuka($sesi); // tak bisa tutup sesi yang sudah tutup

        return DB::transaction(function () use ($sesi, $saldoFisik): PosSesi {
            $saldoSistem = $this->recomputeSaldoSistem($sesi);
            $selisih = round($saldoFisik - $saldoSistem, 2);

            $sesi->update([
                'saldo_fisik' => round($saldoFisik, 2),
                'selisih' => $selisih,
                'status' => 'tutup',
                'ditutup_pada' => now(),
            ]);

            // POS Closing Entry (pola ERPNext): selisih kas masuk Buku Besar.
            $this->postingSelisih($sesi, $selisih);

            return $sesi->fresh();
        });
    }

    /**
     * Posting jurnal selisih kas saat tutup sesi (POS Closing Entry).
     *   selisih > 0 (kas fisik > sistem / LEBIH): Debit Kas, Kredit Selisih Kas.
     *   selisih < 0 (KURANG): Debit Selisih Kas, Kredit Kas.
     * Idempoten via (ref_type=PosSesiSelisih, ref_id=sesi). Menahan diri (null)
     * bila akun kas / akun selisih belum dikonfigurasi — tak menggagalkan tutup sesi.
     */
    private function postingSelisih(PosSesi $sesi, float $selisih): ?Jurnal
    {
        if (abs($selisih) < 0.005) {
            return null; // kas pas → tak perlu jurnal
        }

        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        $akunSelisih = $this->resolveAkunSelisih();

        if ($kas === null || $akunSelisih === null) {
            return null;
        }

        $nominal = abs($selisih);
        $memo = 'Selisih kas tutup sesi POS #'.$sesi->id;

        $baris = $selisih > 0
            ? [
                ['akun_id' => $kas->id, 'debit' => $nominal, 'kredit' => 0, 'memo' => $memo.' (lebih)'],
                ['akun_id' => $akunSelisih->id, 'debit' => 0, 'kredit' => $nominal, 'memo' => $memo],
            ]
            : [
                ['akun_id' => $akunSelisih->id, 'debit' => $nominal, 'kredit' => 0, 'memo' => $memo],
                ['akun_id' => $kas->id, 'debit' => 0, 'kredit' => $nominal, 'memo' => $memo.' (kurang)'],
            ];

        return app(JurnalService::class)->postJurnal([
            'tanggal' => now(),
            'deskripsi' => $memo,
            'tipe_sumber' => 'pos',
            'ref_type' => 'PosSesiSelisih',
            'ref_id' => (string) $sesi->id,
            'dibuat_oleh' => $sesi->kasir_id,
            'baris' => $baris,
        ]);
    }

    /**
     * Akun lawan selisih kas: dari Setting `pos_akun_selisih_id`, atau fallback
     * akun postable bernama "Selisih Kas". Null bila belum disiapkan.
     */
    private function resolveAkunSelisih(): ?Akun
    {
        $id = Setting::getTenantStringValue('pos_akun_selisih_id');
        if (! empty($id)) {
            $akun = Akun::query()->where('is_postable', true)->find($id);
            if ($akun !== null) {
                return $akun;
            }
        }

        return Akun::query()
            ->where('is_postable', true)
            ->where('nama', 'like', '%Selisih Kas%')
            ->orderBy('kode')
            ->first();
    }

    /** Hitung ulang & simpan saldo_sistem dari transaksi sesi. */
    public function recomputeSaldoSistem(PosSesi $sesi): float
    {
        $masuk = (float) $sesi->transaksi()->where('jenis', 'pemasukan')->sum('nominal');
        $keluar = (float) $sesi->transaksi()->where('jenis', 'pengeluaran')->sum('nominal');

        $saldo = round((float) $sesi->saldo_awal + $masuk - $keluar, 2);
        $sesi->update(['saldo_sistem' => $saldo]);

        return $saldo;
    }

    private function ensureBuka(PosSesi $sesi): void
    {
        if ($sesi->status !== 'buka') {
            throw new InvalidArgumentException("Sesi kas berstatus '{$sesi->status}' — hanya sesi 'buka' yang bisa diproses.");
        }
    }

    private function resolveSesi(PosSesi|int $sesi): PosSesi
    {
        return $sesi instanceof PosSesi ? $sesi : PosSesi::query()->findOrFail($sesi);
    }

    private function resolveKategori(PosKategori|int $kategori): PosKategori
    {
        return $kategori instanceof PosKategori ? $kategori : PosKategori::query()->findOrFail($kategori);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
