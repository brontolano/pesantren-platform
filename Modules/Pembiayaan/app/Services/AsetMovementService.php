<?php

namespace Modules\Pembiayaan\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Models\MutasiAset;
use RuntimeException;

/**
 * G2-13 — Mutasi lokasi & pelepasan (disposal) aset tetap.
 *
 *  - mutasiLokasi: pindah lokasi fisik, tanpa jurnal.
 *  - disposal NON-WAKAF: posting laba/rugi pelepasan ke buku besar
 *      D Kas (proceeds) + D Akumulasi Penyusutan + D Rugi (bila rugi)
 *      / K Aset (harga perolehan) + K Laba (bila untung).
 *  - disposal WAKAF: TIDAK mengakui laba/rugi (UU 41/2004) — hanya dicatat
 *      sebagai mutasi (tanpa_pl), tanpa jurnal P/L. Pokok wakaf tak boleh berkurang.
 *
 * Disposal melewati gerbang persetujuan F1 (Approvable, no-op tanpa aturan wajib).
 */
class AsetMovementService
{
    /** gap-96c — langkah perbaikan ramah-pengguna untuk pesan COA belum siap. */
    private const COA_LANGKAH = 'Minta admin keuangan menyiapkan Bagan Akun (Pengaturan Keuangan) terlebih dahulu, lalu ulangi pelepasan aset ini.';

    /** gap-96c — pesan utama saat bagan akun inti (kas/aset tetap) belum tersedia. */
    private const COA_BELUM_SIAP = 'Bagan akun keuangan belum siap untuk mencatat pelepasan aset (akun Kas/Aset Tetap belum tersedia). ' . self::COA_LANGKAH;

    public function __construct(private readonly JurnalService $jurnalService)
    {
    }

    /** Pindah lokasi aset (tanpa dampak buku besar). */
    public function mutasiLokasi(Aset $aset, string $keLokasi, array $opsi = []): MutasiAset
    {
        $this->ensureTenantContext();
        if ($aset->status_aset === 'dilepas') {
            throw new InvalidArgumentException('Aset sudah dilepas — tidak dapat dimutasi.');
        }

        $dari = $aset->lokasi;
        $mutasi = MutasiAset::create([
            'aset_id' => $aset->id,
            'tipe' => 'mutasi_lokasi',
            'tanggal' => $opsi['tanggal'] ?? Carbon::now()->toDateString(),
            'dari_lokasi' => $dari,
            'ke_lokasi' => $keLokasi,
            'keterangan' => $opsi['keterangan'] ?? null,
            'status' => 'posted',
            'created_by' => $opsi['created_by'] ?? null,
        ]);

        $aset->update(['lokasi' => $keLokasi]);

        return $mutasi->fresh();
    }

    /**
     * Pelepasan aset. WAKAF → tanpa P/L. Non-wakaf → posting laba/rugi.
     *
     * @param  array{harga_jual?:float, tanggal?:string, keterangan?:string, created_by?:?int}  $data
     */
    public function disposal(Aset $aset, array $data = []): MutasiAset
    {
        $this->ensureTenantContext();
        if ($aset->status_aset === 'dilepas') {
            throw new InvalidArgumentException('Aset sudah dilepas sebelumnya.');
        }

        $hargaJual = round((float) ($data['harga_jual'] ?? 0), 2);
        if ($hargaJual < 0) {
            throw new InvalidArgumentException('Harga jual tidak boleh negatif.');
        }
        $nilaiBuku = $aset->nilaiBuku();
        $tanpaPl = (bool) $aset->is_wakaf;
        $labaRugi = $tanpaPl ? 0.0 : round($hargaJual - $nilaiBuku, 2);

        // Pra-validasi (non-wakaf): pastikan akun & nilai layak SEBELUM membuat
        // record — mencegah draft yatim & mengganti error 'tidak seimbang' yang
        // menyesatkan dengan pesan akun-belum-di-seed yang deskriptif.
        $cost = round((float) ($aset->nilai_perolehan ?: $aset->nilai), 2);
        $akum = round((float) $aset->akumulasi_penyusutan, 2);
        if (! $tanpaPl) {
            if ($cost <= 0) {
                throw new InvalidArgumentException('Nilai perolehan aset 0 — tidak dapat membuat jurnal pelepasan.');
            }
            $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
            $asetAkun = $this->akunAsetUntuk($aset);
            $akumAkun = Akun::where('kode', '1-2900')->first();
            $labaAkun = Akun::where('kode', '4-6000')->first();
            $rugiAkun = Akun::where('kode', '5-8000')->first();
            // gap-96c — Guard ramah: bila bagan akun (COA) belum disiapkan, beri pesan
            // yang dapat dipahami staf + langkah perbaikan (buka Pengaturan Keuangan →
            // siapkan/seed bagan akun), BUKAN istilah teknis "belum di-seed".
            if ($kas === null || $asetAkun === null) {
                throw new RuntimeException(self::COA_BELUM_SIAP);
            }
            if ($akum > 0 && $akumAkun === null) {
                throw new RuntimeException('Bagan akun belum lengkap: akun Akumulasi Penyusutan (1-2900) belum tersedia. ' . self::COA_LANGKAH);
            }
            if ($labaRugi < 0 && $rugiAkun === null) {
                throw new RuntimeException('Bagan akun belum lengkap: akun Rugi Pelepasan Aset (5-8000) belum tersedia. ' . self::COA_LANGKAH);
            }
            if ($labaRugi > 0 && $labaAkun === null) {
                throw new RuntimeException('Bagan akun belum lengkap: akun Laba Pelepasan Aset (4-6000) belum tersedia. ' . self::COA_LANGKAH);
            }
        }

        $mutasi = MutasiAset::create([
            'aset_id' => $aset->id,
            'tipe' => 'disposal',
            'tanggal' => $data['tanggal'] ?? Carbon::now()->toDateString(),
            'harga_jual' => $hargaJual,
            'nilai_buku' => $nilaiBuku,
            'laba_rugi' => $labaRugi,
            'tanpa_pl' => $tanpaPl,
            'keterangan' => $data['keterangan'] ?? null,
            'status' => 'draft',
            'created_by' => $data['created_by'] ?? null,
        ]);

        // Gerbang persetujuan F1 (no-op bila tak ada aturan wajib terpicu).
        // Dipanggil di luar transaksi agar draft tetap ada untuk disetujui kelak.
        $mutasi->pastikanBolehLanjut();

        return DB::transaction(function () use ($mutasi, $aset, $hargaJual, $tanpaPl, $cost, $akum, $labaRugi, $data): MutasiAset {
            // Kunci baris aset & re-cek status DI DALAM lock (anti pelepasan ganda/race).
            $terkunci = Aset::whereKey($aset->id)->lockForUpdate()->first();
            if ($terkunci === null) {
                throw new RuntimeException('Aset tidak ditemukan saat penguncian.');
            }
            if ($terkunci->status_aset === 'dilepas') {
                throw new InvalidArgumentException('Aset sudah dilepas (pelepasan ganda ditolak).');
            }

            // WAKAF: catat pelepasan TANPA mengakui laba/rugi (tanpa jurnal P/L).
            if ($tanpaPl) {
                $mutasi->update(['status' => 'posted']);
                $terkunci->update(['status_aset' => 'dilepas']);

                return $mutasi->fresh();
            }

            $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
            $asetAkun = $this->akunAsetUntuk($aset);
            $akumAkun = Akun::where('kode', '1-2900')->first();
            $labaAkun = Akun::where('kode', '4-6000')->first();
            $rugiAkun = Akun::where('kode', '5-8000')->first();

            $baris = [];
            if ($hargaJual > 0) {
                $baris[] = ['akun_id' => $kas->id, 'debit' => $hargaJual, 'kredit' => 0, 'memo' => 'Penerimaan pelepasan aset'];
            }
            if ($akum > 0) {
                $baris[] = ['akun_id' => $akumAkun->id, 'debit' => $akum, 'kredit' => 0, 'memo' => 'Hapus akumulasi penyusutan'];
            }
            if ($labaRugi < 0) {
                $baris[] = ['akun_id' => $rugiAkun->id, 'debit' => abs($labaRugi), 'kredit' => 0, 'memo' => 'Rugi pelepasan aset'];
            }
            $baris[] = ['akun_id' => $asetAkun->id, 'debit' => 0, 'kredit' => $cost, 'memo' => 'Hapus harga perolehan aset'];
            if ($labaRugi > 0) {
                $baris[] = ['akun_id' => $labaAkun->id, 'debit' => 0, 'kredit' => $labaRugi, 'memo' => 'Laba pelepasan aset'];
            }

            $jurnal = $this->jurnalService->postJurnal([
                'tanggal' => $mutasi->tanggal,
                'deskripsi' => 'Pelepasan aset — ' . $aset->nama_aset,
                'tipe_sumber' => 'disposal_aset',
                'ref_type' => 'MutasiAset',
                'ref_id' => (string) $mutasi->id,
                'dibuat_oleh' => $data['created_by'] ?? null,
                'baris' => $baris,
            ]);

            $mutasi->update(['status' => 'posted', 'jurnal_id' => $jurnal->id]);
            $terkunci->update(['status_aset' => 'dilepas']);

            return $mutasi->fresh();
        });
    }

    /** Akun aset tetap (postable) berdasarkan jenis; fallback Peralatan & Inventaris. */
    private function akunAsetUntuk(Aset $aset): ?Akun
    {
        $kode = match (strtolower((string) $aset->jenis)) {
            'tanah' => '1-2100',
            'bangunan', 'gedung' => '1-2200',
            default => '1-2300',
        };

        return Akun::where('kode', $kode)->first() ?? Akun::where('kode', '1-2300')->first();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
