<?php

namespace Modules\AI\Services\Guardrails;

use Modules\AI\Services\Gateway\AiGenerateResult;
use Modules\AI\Services\Rag\RetrievalResult;

/**
 * T-A022 — Citation enforcer (Blueprint §4.2 langkah 6, PRD §7.5).
 *
 * MEMVERIFIKASI jawaban tidak keluar dari konteks retrieval — sitasi sendiri
 * sudah dilampirkan pipeline secara deterministik (T-A031); model TIDAK
 * PERNAH diminta mengarang sitasi, dan enforcer ini juga TIDAK memintanya.
 *
 * Heuristik DETERMINISTIK TANPA LLM:
 *  (a) jawaban degraded / ditolakPolicy → lolos otomatis (pesan prosedural,
 *      bukan klaim berkonteks — menjalankannya lewat heuristik menyesatkan);
 *  (b) token-overlap per kalimat: kata konten (≥ min huruf, stopword teknis
 *      ID di-skip — daftar di config, BUKAN konten keagamaan) dibandingkan
 *      dengan himpunan token gabungan chunk konteks. Kalimat dengan overlap
 *      < ambang_overlap_kalimat = "di luar konteks". Proporsi kalimat-luar
 *      > ambang_proporsi_luar ATAU jawaban > rasio_panjang_max × panjang
 *      konteks → TIDAK lolos;
 *  (c) kategori 'agama' tanpa konteks sama sekali → TIDAK lolos (PRD §7.5:
 *      jawaban agama tanpa rujukan tidak pernah terkirim). Kategori lain
 *      tanpa konteks → lolos (tidak wajib rujukan).
 *
 * Ambang hidup di config ai.guardrails.enforcer (Aturan Emas #11).
 */
class CitationEnforcer
{
    public const KATEGORI_AGAMA = 'agama';

    public function verifikasi(
        AiGenerateResult $hasil,
        RetrievalResult $retrieval,
        string $kategori = 'umum',
    ): EnforcementVerdict {
        // (a) Bukan klaim berkonteks — jangan dihukum heuristik.
        if ($hasil->degraded || $hasil->ditolakPolicy) {
            return new EnforcementVerdict(
                lolos: true,
                skorOverlap: 1.0,
                alasan: 'jawaban degradasi/tolakan kebijakan — bukan klaim berkonteks',
            );
        }

        $konteks = trim(implode("\n", array_map(
            static fn (array $item): string => (string) $item['chunk']->konten,
            $retrieval->chunks,
        )));

        // (c) Tanpa konteks: agama fail-closed, kategori lain lolos.
        if ($konteks === '') {
            if ($kategori === self::KATEGORI_AGAMA) {
                return new EnforcementVerdict(
                    lolos: false,
                    skorOverlap: 0.0,
                    alasan: 'jawaban kategori agama tanpa konteks rujukan sama sekali',
                );
            }

            return new EnforcementVerdict(
                lolos: true,
                skorOverlap: 1.0,
                alasan: "tanpa konteks — kategori '{$kategori}' tidak wajib rujukan",
            );
        }

        // (b) Token-overlap per kalimat terhadap himpunan token konteks.
        $tokenKonteks = array_flip($this->tokenKonten($konteks));

        $kalimat = preg_split('/(?<=[.!?])\s+|\n+/u', trim($hasil->text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $ambangKalimat = (float) config('ai.guardrails.enforcer.ambang_overlap_kalimat', 0.25);
        $ambangLuar = (float) config('ai.guardrails.enforcer.ambang_proporsi_luar', 0.35);
        $rasioMax = (float) config('ai.guardrails.enforcer.rasio_panjang_max', 3.0);

        $dihitung = 0;
        $diLuar = 0;
        $jumlahOverlap = 0.0;

        foreach ($kalimat as $k) {
            $token = $this->tokenKonten($k);

            if ($token === []) {
                continue; // kalimat tanpa kata konten (sapaan pendek, dst.)
            }

            $cocok = 0;
            foreach ($token as $t) {
                if (isset($tokenKonteks[$t])) {
                    $cocok++;
                }
            }

            $overlap = $cocok / count($token);
            $jumlahOverlap += $overlap;
            $dihitung++;

            if ($overlap < $ambangKalimat) {
                $diLuar++;
            }
        }

        if ($dihitung === 0) {
            // Tidak ada kalimat berkata-konten = tidak ada klaim untuk diuji.
            return new EnforcementVerdict(
                lolos: true,
                skorOverlap: 1.0,
                alasan: 'jawaban tanpa kalimat berkata-konten — tidak ada klaim untuk diuji',
            );
        }

        $skor = $jumlahOverlap / $dihitung;

        // Pagar panjang: jawaban jauh lebih panjang dari konteks = indikasi
        // mengarang di luar rujukan walau sebagian kalimat overlap.
        if (mb_strlen($hasil->text) > $rasioMax * mb_strlen($konteks)) {
            return new EnforcementVerdict(
                lolos: false,
                skorOverlap: $skor,
                alasan: sprintf('panjang jawaban melebihi %.1f× panjang konteks', $rasioMax),
            );
        }

        $proporsiLuar = $diLuar / $dihitung;

        if ($proporsiLuar > $ambangLuar) {
            return new EnforcementVerdict(
                lolos: false,
                skorOverlap: $skor,
                alasan: sprintf(
                    '%d dari %d kalimat di luar konteks (proporsi %.2f > ambang %.2f)',
                    $diLuar,
                    $dihitung,
                    $proporsiLuar,
                    $ambangLuar,
                ),
            );
        }

        return new EnforcementVerdict(
            lolos: true,
            skorOverlap: $skor,
            alasan: sprintf('lolos: %d/%d kalimat berakar konteks', $dihitung - $diLuar, $dihitung),
        );
    }

    /**
     * Token kata konten: huruf/angka unicode, lowercase, panjang ≥ min_kata
     * huruf, stopword teknis ID (config) di-skip. Unik per teks.
     *
     * @return list<string>
     */
    private function tokenKonten(string $teks): array
    {
        $minHuruf = (int) config('ai.guardrails.enforcer.min_kata', 4);
        $stopword = array_flip(array_map(
            static fn ($s): string => mb_strtolower((string) $s),
            (array) config('ai.guardrails.enforcer.stopwords', []),
        ));

        $kata = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $hasil = [];
        foreach ($kata as $k) {
            if (mb_strlen($k) < $minHuruf || isset($stopword[$k])) {
                continue;
            }
            $hasil[$k] = true;
        }

        return array_keys($hasil);
    }
}
