<?php

namespace Modules\Ledger\Services;

use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\SumberDana;
use RuntimeException;

/**
 * Pengelolaan Dana Kebajikan / Pembersihan Harta (uang syubhat/non-halal).
 *
 * Uang syubhat dicatat sebagai titipan untuk dibersihkan (akun "Dana Kebajikan"),
 * BUKAN pendapatan. Penyaluran hanya ke whitelist (fasilitas umum/dhuafa/operasional),
 * dilarang untuk ibadah — ditegakkan KehalalanGuard di dalam JurnalService.
 *
 * Niat selalu `pelepasan` (bukan sedekah/pahala).
 */
class DanaKebajikanService
{
    public const AKUN_DANA_KEBAJIKAN = '2-2000';

    public function __construct(private readonly JurnalService $jurnalService) {}

    /**
     * Terima dana syubhat: Debit Kas, Kredit Dana Kebajikan (bukan pendapatan).
     *
     * @param  ?string  $refId  Penambat idempotensi dari dokumen sumber (mis. id penerimaan
     *                          atau token form). Bila null, posting diperlakukan boleh-ganda
     *                          secara EKSPLISIT — pemanggil UI WAJIB mengisi ini agar submit
     *                          ulang tak menggandakan uang.
     */
    public function terima(float $nominal, string $kodeSumberSyubhat, ?int $dibuatOleh = null, ?string $keterangan = null, ?string $refId = null): Jurnal
    {
        $sumber = $this->sumberSyubhat($kodeSumberSyubhat);
        $kas = $this->akunKas();
        $danaKebajikan = $this->akunDanaKebajikan();

        return $this->jurnalService->postJurnal($this->dedupKey($refId, 'DanaKebajikan:terima', [
            'deskripsi' => 'Penerimaan dana kebajikan/pembersihan (pelepasan) — '.($keterangan ?? $sumber->nama_sumber),
            'tipe_sumber' => 'kebajikan',
            'dibuat_oleh' => $dibuatOleh,
            'baris' => [
                ['akun_id' => $kas->id, 'sumber_dana_id' => $sumber->id, 'debit' => $nominal, 'memo' => 'Kas masuk (pelepasan)'],
                ['akun_id' => $danaKebajikan->id, 'sumber_dana_id' => $sumber->id, 'kredit' => $nominal, 'memo' => 'Titipan dana kebajikan'],
            ],
        ]));
    }

    /**
     * Salurkan dana kebajikan ke akun penyaluran (sub-akun bertuju, peruntukan terisi).
     * Jurnal: Debit <akun penyaluran KEWAJIBAN> / Kredit Kas — mengurangi titipan & kas,
     * TIDAK menyentuh surplus/defisit. Guard menolak bila tujuan = ibadah/masjid atau
     * peruntukan tidak diizinkan kebijakan tenant.
     */
    public function salurkan(float $nominal, string $kodeAkunPenyaluran, string $kodeSumberSyubhat, ?int $dibuatOleh = null, ?string $keterangan = null, ?string $refId = null): Jurnal
    {
        $sumber = $this->sumberSyubhat($kodeSumberSyubhat);
        $kas = $this->akunKas();
        $tujuan = Akun::where('kode', $kodeAkunPenyaluran)
            ->firstOr(fn () => throw new RuntimeException("Akun penyaluran {$kodeAkunPenyaluran} tidak ditemukan."));

        return $this->jurnalService->postJurnal($this->dedupKey($refId, 'DanaKebajikan:salurkan', [
            'deskripsi' => 'Penyaluran dana kebajikan — '.($keterangan ?? $tujuan->nama),
            'tipe_sumber' => 'kebajikan',
            'dibuat_oleh' => $dibuatOleh,
            'baris' => [
                ['akun_id' => $tujuan->id, 'sumber_dana_id' => $sumber->id, 'debit' => $nominal, 'memo' => 'Penyaluran (pelepasan)'],
                ['akun_id' => $kas->id, 'sumber_dana_id' => $sumber->id, 'kredit' => $nominal, 'memo' => 'Kas keluar'],
            ],
        ]));
    }

    /**
     * Tambatkan kunci idempotensi ke payload postJurnal: bila $refId ada → keyed
     * (ref_type+ref_id); bila tidak → tandai allow_duplicate=true secara eksplisit
     * agar guard JurnalService tak menolak, sembari menyuratkan risiko ganda.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function dedupKey(?string $refId, string $refType, array $payload): array
    {
        if ($refId !== null) {
            $payload['ref_type'] = $refType;
            $payload['ref_id'] = $refId;
        } else {
            $payload['allow_duplicate'] = true;
        }

        return $payload;
    }

    /**
     * Saldo dana kebajikan yang belum disalurkan = Σkredit penerimaan (2-2000)
     * − Σdebit penyaluran (sub-akun 2-20xx). Dihitung dari keluarga akun Dana Kebajikan.
     */
    public function saldo(): float
    {
        $induk = $this->akunDanaKebajikan();
        $ids = array_merge([$induk->id], $induk->children()->pluck('id')->all());

        $kredit = (float) JurnalBaris::whereIn('akun_id', $ids)->sum('kredit');
        $debit = (float) JurnalBaris::whereIn('akun_id', $ids)->sum('debit');

        return round($kredit - $debit, 2);
    }

    private function sumberSyubhat(string $kode): SumberDana
    {
        $sumber = SumberDana::where('kode_dana', $kode)->first();
        if ($sumber === null || ! $sumber->bukanHalal()) {
            throw new RuntimeException("Sumber dana {$kode} bukan dana syubhat/non-halal.");
        }

        return $sumber;
    }

    private function akunKas(): Akun
    {
        return Akun::kasBank()->where('is_postable', true)->orderBy('kode')
            ->firstOr(fn () => throw new RuntimeException('Akun kas/bank belum di-seed.'));
    }

    private function akunDanaKebajikan(): Akun
    {
        return Akun::where('kode', self::AKUN_DANA_KEBAJIKAN)
            ->firstOr(fn () => throw new RuntimeException('Akun Dana Kebajikan (2-2000) belum di-seed.'));
    }
}
