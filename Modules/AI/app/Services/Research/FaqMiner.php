<?php

namespace Modules\AI\Services\Research;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiFaqDraf;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Privacy\PiiRedactor;
use Modules\AI\Services\Rag\IngestService;

/**
 * FAQ-Mining (permintaan owner 2026-06-11) — chatbot "belajar dari kebiasaan
 * pengguna" secara HUMAN-IN-THE-LOOP, TANPA training model.
 *
 * Alur: tambang() mengumpulkan pertanyaan berulang dari ai_escalation
 * (pertanyaan_ringkas, kategori non-sensitif) + ai_message arah masuk
 * (percakapan non-handoff) → normalisasi → clustering TEKNIK KLASIK LOKAL
 * (token-set Jaccard ≥ ambang ATAU kandungan kata kunci utama — TANPA LLM)
 * → upsert ai_faq_draf status 'usulan'. Admin/ustadz mengisi jawaban lalu
 * sahkan() → teks FAQ diingest ke Sumber Pengetahuan kategori 'faq'
 * (IngestService) → chatbot otomatis dapat menjawabnya via RAG.
 *
 * ATURAN EMAS yang ditegakkan di sini:
 *  - human-in-the-loop MUTLAK: tanpa sahkan() TIDAK ADA yang masuk RAG;
 *  - PRIVASI by-construction: kedua sumber teks SUDAH terredaksi PII saat
 *    ditulis (PolicyEngine/WaChatService) — dan FaqMiner tetap meredaksi
 *    PAKSA ulang sebelum menyimpan draf (sabuk pengaman ganda, idempoten);
 *  - kategori eskalasi sensitif (config faq_mining.kategori_skip:
 *    blacklist_topik/level_fatwa) TIDAK PERNAH ditambang — wilayah ustadz;
 *  - keputusan manusia dihormati: cluster yang drafnya sudah
 *    disahkan/diabaikan TIDAK diusulkan ulang.
 *
 * SELURUH method berjalan dalam KONTEKS TENANT (tabel ai_* tenant).
 */
class FaqMiner
{
    public function __construct(
        private readonly PiiRedactor $redactor,
        private readonly IngestService $ingest,
    ) {}

    // ------------------------------------------------------------------
    // Penambangan
    // ------------------------------------------------------------------

    /**
     * Tambang pertanyaan berulang dalam jendela $periodeHari (default config)
     * → upsert ai_faq_draf. Idempoten: dijalankan berulang dengan data sama
     * tidak menggandakan baris (kunci = hash token ternormalisasi pertanyaan
     * perwakilan); frekuensi & variasi diperbarui mengikuti jendela terkini.
     *
     * @return array{usulan_baru: int, diperbarui: int, total_pertanyaan: int}
     */
    public function tambang(?int $periodeHari = null): array
    {
        $periodeHari = $periodeHari ?? (int) config('ai.faq_mining.periode_hari', 30);
        $batas = now()->subDays(max(1, $periodeHari));

        $pertanyaan = $this->kumpulkanPertanyaan($batas);
        $cluster = $this->kelompokkan($pertanyaan);

        $minFrekuensi = max(1, (int) config('ai.faq_mining.min_frekuensi', 3));
        $usulanBaru = 0;
        $diperbarui = 0;

        foreach ($cluster as $kelompok) {
            if ($kelompok['frekuensi'] < $minFrekuensi) {
                continue;
            }

            if ($this->upsertDraf($kelompok)) {
                $usulanBaru++;
            } else {
                $diperbarui++;
            }
        }

        return [
            'usulan_baru' => $usulanBaru,
            'diperbarui' => $diperbarui,
            'total_pertanyaan' => count($pertanyaan),
        ];
    }

    // ------------------------------------------------------------------
    // Aksi human-in-the-loop
    // ------------------------------------------------------------------

    /**
     * Simpan jawaban admin/ustadz pada sebuah usulan (status → dijawab).
     * Belum menyentuh RAG — pengesahan tetap langkah terpisah yang sadar.
     */
    public function isiJawaban(int $id, User $user, string $jawaban): AiFaqDraf
    {
        $this->pastikanBoleh($user);

        $jawaban = trim($jawaban);

        if ($jawaban === '') {
            throw new InvalidArgumentException('Jawaban tidak boleh kosong.');
        }

        /** @var AiFaqDraf $draf */
        $draf = AiFaqDraf::query()->findOrFail($id);

        if (! in_array($draf->status, [AiFaqDraf::STATUS_USULAN, AiFaqDraf::STATUS_DIJAWAB], true)) {
            throw new InvalidArgumentException(
                "Usulan FAQ #{$id} berstatus '{$draf->status}' — jawaban hanya dapat diisi pada usulan/dijawab."
            );
        }

        $draf->update([
            'jawaban' => $jawaban,
            'status' => AiFaqDraf::STATUS_DIJAWAB,
        ]);

        return $draf->refresh();
    }

    /**
     * SAHKAN usulan FAQ — satu-satunya pintu masuk hasil tambang ke RAG.
     * Wajib: permission `ai.pengetahuan.kelola` + jawaban sudah terisi.
     * Teks "T: ... J: ..." (+ variasi) diingest via IngestService kategori
     * 'faq' → source_id tersimpan + status disahkan → chatbot otomatis bisa
     * menjawabnya via retrieval RAG begitu ingest selesai.
     */
    public function sahkan(int $id, User $user): AiFaqDraf
    {
        $this->pastikanBoleh($user);

        /** @var AiFaqDraf $draf */
        $draf = AiFaqDraf::query()->findOrFail($id);

        if (! in_array($draf->status, [AiFaqDraf::STATUS_USULAN, AiFaqDraf::STATUS_DIJAWAB], true)) {
            throw new InvalidArgumentException(
                "Usulan FAQ #{$id} berstatus '{$draf->status}' — hanya usulan/dijawab yang dapat disahkan."
            );
        }

        if (trim((string) $draf->jawaban) === '') {
            throw new InvalidArgumentException(
                'Mohon isi jawaban terlebih dahulu — usulan FAQ tanpa jawaban tidak dapat disahkan.'
            );
        }

        $source = $this->ingest->daftarkanSumber(
            'FAQ: '.Str::limit($draf->pertanyaan_perwakilan, 80, '…'),
            'faq',
            $this->renderTeksFaq($draf),
            $user,
        );

        $draf->update([
            'source_id' => $source->getKey(),
            'status' => AiFaqDraf::STATUS_DISAHKAN,
            'disahkan_oleh' => $user->getKey(),
            'disahkan_pada' => now(),
        ]);

        return $draf->refresh();
    }

    /** Abaikan usulan — keputusan diingat: cluster ini tak diusulkan ulang. */
    public function abaikan(int $id, User $user): AiFaqDraf
    {
        $this->pastikanBoleh($user);

        /** @var AiFaqDraf $draf */
        $draf = AiFaqDraf::query()->findOrFail($id);

        if ($draf->status === AiFaqDraf::STATUS_DISAHKAN) {
            throw new InvalidArgumentException(
                "Usulan FAQ #{$id} sudah disahkan — kelola sumbernya lewat halaman Sumber Pengetahuan."
            );
        }

        $draf->update(['status' => AiFaqDraf::STATUS_DIABAIKAN]);

        return $draf->refresh();
    }

    // ------------------------------------------------------------------
    // Pengumpulan sumber pertanyaan (SEMUA sudah terredaksi by-construction)
    // ------------------------------------------------------------------

    /**
     * @return list<array{teks: string, sumber: string}>
     */
    private function kumpulkanPertanyaan(\DateTimeInterface $batas): array
    {
        $maks = max(1, (int) config('ai.faq_mining.maks_pertanyaan', 2000));
        $kategoriSkip = (array) config('ai.faq_mining.kategori_skip', ['blacklist_topik', 'level_fatwa']);

        $hasil = [];

        // 1. Eskalasi "Tanya Ustadz" — pertanyaan_ringkas terredaksi.
        //    Kategori sensitif (hukum/blacklist) DILEWATI: wilayah ustadz.
        AiEscalation::query()
            ->where('created_at', '>=', $batas)
            ->whereNotIn('kategori', $kategoriSkip)
            ->orderBy('id')
            ->limit($maks)
            ->pluck('pertanyaan_ringkas')
            ->each(function ($teks) use (&$hasil): void {
                $hasil[] = ['teks' => (string) $teks, 'sumber' => AiFaqDraf::SUMBER_ESKALASI];
            });

        // 2. Pesan masuk percakapan chatbot — isi_terredaksi; percakapan
        //    status handoff dilewati (sedang ditangani manusia).
        \Modules\AI\Models\AiMessage::query()
            ->where('arah', \Modules\AI\Models\AiMessage::ARAH_MASUK)
            ->where('created_at', '>=', $batas)
            ->whereHas('conversation', fn ($q) => $q->where('status', '!=', AiConversation::STATUS_HANDOFF))
            ->orderBy('id')
            ->limit($maks)
            ->pluck('isi_terredaksi')
            ->each(function ($teks) use (&$hasil): void {
                $hasil[] = ['teks' => (string) $teks, 'sumber' => AiFaqDraf::SUMBER_PERCAKAPAN];
            });

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Clustering klasik lokal (TANPA LLM)
    // ------------------------------------------------------------------

    /**
     * Kelompokkan pertanyaan mirip secara greedy & deterministik:
     *  1. group identik per string ternormalisasi (varian asli + hitungan);
     *  2. urutkan group (frekuensi turun, lalu kunci naik) — group dominan
     *     menjadi jangkar cluster (representatif stabil antar-run);
     *  3. group bergabung ke cluster pertama yang mirip dengan jangkarnya:
     *     Jaccard ≥ ambang_jaccard ATAU (irisan ≥ 2 token DAN
     *     irisan/min(|A|,|B|) ≥ ambang_kandungan — "kata kunci utama").
     *
     * @param  list<array{teks: string, sumber: string}>  $pertanyaan
     * @return list<array{perwakilan: string, hash: string, variasi: list<string>, frekuensi: int, sumber: string}>
     */
    private function kelompokkan(array $pertanyaan): array
    {
        $ambangJaccard = (float) config('ai.faq_mining.ambang_jaccard', 0.6);
        $ambangKandungan = (float) config('ai.faq_mining.ambang_kandungan', 0.8);
        $maksVariasi = max(0, (int) config('ai.faq_mining.maks_variasi', 10));

        // Tahap 1 — group per bentuk ternormalisasi.
        /** @var array<string, array{tokens: list<string>, frekuensi: int, varian: array<string, int>, eskalasi: bool}> $groups */
        $groups = [];

        foreach ($pertanyaan as $item) {
            $tokens = $this->normalisasi($item['teks']);

            // < 2 token konten = bukan pertanyaan bermakna (perintah MENU,
            // sapaan, tanggal verifikasi, dll) — lewati.
            if (count($tokens) < 2) {
                continue;
            }

            $kunci = implode(' ', $tokens);
            $teksRapi = trim((string) preg_replace('/\s+/u', ' ', $item['teks']));

            if (! isset($groups[$kunci])) {
                $groups[$kunci] = [
                    'tokens' => $tokens,
                    'frekuensi' => 0,
                    'varian' => [],
                    'eskalasi' => false,
                ];
            }

            $groups[$kunci]['frekuensi']++;
            $groups[$kunci]['varian'][$teksRapi] = ($groups[$kunci]['varian'][$teksRapi] ?? 0) + 1;
            $groups[$kunci]['eskalasi'] = $groups[$kunci]['eskalasi']
                || $item['sumber'] === AiFaqDraf::SUMBER_ESKALASI;
        }

        // Tahap 2 — urut deterministik: frekuensi turun, kunci naik.
        uksort($groups, function (string $a, string $b) use ($groups): int {
            return [$groups[$b]['frekuensi'], $a] <=> [$groups[$a]['frekuensi'], $b];
        });

        // Tahap 3 — greedy merge ke cluster berjangkar.
        /** @var list<array{anchor: list<string>, frekuensi: int, varian: array<string, int>, eskalasi: bool}> $clusters */
        $clusters = [];

        foreach ($groups as $group) {
            $tergabung = false;

            foreach ($clusters as $i => $cluster) {
                if ($this->mirip($group['tokens'], $cluster['anchor'], $ambangJaccard, $ambangKandungan)) {
                    $clusters[$i]['frekuensi'] += $group['frekuensi'];
                    $clusters[$i]['eskalasi'] = $cluster['eskalasi'] || $group['eskalasi'];

                    foreach ($group['varian'] as $teks => $jumlah) {
                        $clusters[$i]['varian'][$teks] = ($cluster['varian'][$teks] ?? 0) + $jumlah;
                    }

                    $tergabung = true;

                    break;
                }
            }

            if (! $tergabung) {
                $clusters[] = [
                    'anchor' => $group['tokens'],
                    'frekuensi' => $group['frekuensi'],
                    'varian' => $group['varian'],
                    'eskalasi' => $group['eskalasi'],
                ];
            }
        }

        // Bentuk hasil: perwakilan = varian terbanyak (seri → terpendek, abjad).
        $hasil = [];

        foreach ($clusters as $cluster) {
            $varian = $cluster['varian'];
            uksort($varian, function (string $a, string $b) use ($varian): int {
                return [$varian[$b], mb_strlen($a), $a] <=> [$varian[$a], mb_strlen($b), $b];
            });

            $teksVarian = array_keys($varian);
            $perwakilan = array_shift($teksVarian);

            $hasil[] = [
                'perwakilan' => (string) $perwakilan,
                'hash' => hash('sha256', implode(' ', $cluster['anchor'])),
                'variasi' => array_slice($teksVarian, 0, $maksVariasi),
                'frekuensi' => $cluster['frekuensi'],
                'sumber' => $cluster['eskalasi'] ? AiFaqDraf::SUMBER_ESKALASI : AiFaqDraf::SUMBER_PERCAKAPAN,
            ];
        }

        return $hasil;
    }

    /**
     * Normalisasi teks → token konten unik terurut: lowercase, buang token
     * redaksi [PII:...], buang tanda baca & angka, buang stopword ID ringan
     * (config), buang token < 3 huruf.
     *
     * @return list<string>
     */
    private function normalisasi(string $teks): array
    {
        $teks = mb_strtolower($teks);
        // Token redaksi [pii:hp:1] jangan jadi "kata kunci".
        $teks = (string) preg_replace('/\[pii:[^\]]*\]/u', ' ', $teks);
        // Hanya huruf yang tersisa (tanda baca + angka → spasi).
        $teks = (string) preg_replace('/[^\p{L}]+/u', ' ', $teks);

        $stopwords = (array) config('ai.faq_mining.stopwords', []);

        $tokens = array_values(array_unique(array_filter(
            preg_split('/\s+/u', trim($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $t): bool => mb_strlen($t) >= 3 && ! in_array($t, $stopwords, true),
        )));

        sort($tokens);

        return $tokens;
    }

    /**
     * Dua token-set dianggap satu kelompok bila Jaccard ≥ ambang ATAU
     * kata kunci utama beririsan kuat (≥ 2 token sama DAN irisan menutup
     * ≥ ambang_kandungan dari set terpendek — pertanyaan pendek ⊂ panjang).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function mirip(array $a, array $b, float $ambangJaccard, float $ambangKandungan): bool
    {
        $irisan = count(array_intersect($a, $b));

        if ($irisan === 0) {
            return false;
        }

        $gabungan = count($a) + count($b) - $irisan;

        if ($gabungan > 0 && ($irisan / $gabungan) >= $ambangJaccard) {
            return true;
        }

        $minimal = min(count($a), count($b));

        return $irisan >= 2 && $minimal > 0 && ($irisan / $minimal) >= $ambangKandungan;
    }

    // ------------------------------------------------------------------
    // Upsert draf
    // ------------------------------------------------------------------

    /**
     * Upsert satu cluster → ai_faq_draf. Mengembalikan true bila baris BARU
     * tercipta. Keputusan manusia dihormati: draf ber-hash sama yang sudah
     * disahkan/diabaikan dilewati senyap (tidak diusulkan ulang).
     *
     * @param  array{perwakilan: string, hash: string, variasi: list<string>, frekuensi: int, sumber: string}  $kelompok
     */
    private function upsertDraf(array $kelompok): bool
    {
        // Sabuk pengaman ganda: sumber sudah terredaksi by-construction,
        // tetap redaksi PAKSA ulang (idempoten) sebelum menyimpan draf.
        $perwakilan = $this->redactor->redactPaksa($kelompok['perwakilan'])->text;
        $variasi = array_values(array_filter(array_map(
            fn (string $v): string => $this->redactor->redactPaksa($v)->text,
            $kelompok['variasi'],
        ), fn (string $v): bool => $v !== $perwakilan));

        /** @var AiFaqDraf|null $ada */
        $ada = AiFaqDraf::query()->where('pertanyaan_hash', $kelompok['hash'])->first();

        if ($ada !== null) {
            if (in_array($ada->status, [AiFaqDraf::STATUS_DISAHKAN, AiFaqDraf::STATUS_DIABAIKAN], true)) {
                return false; // keputusan manusia final — jangan usulkan ulang.
            }

            $maksVariasi = max(0, (int) config('ai.faq_mining.maks_variasi', 10));

            $ada->update([
                'frekuensi' => $kelompok['frekuensi'],
                'variasi' => array_slice(array_values(array_unique(array_merge(
                    (array) $ada->variasi,
                    $variasi,
                ))), 0, $maksVariasi),
                'sumber' => $ada->sumber === AiFaqDraf::SUMBER_ESKALASI
                    ? AiFaqDraf::SUMBER_ESKALASI
                    : $kelompok['sumber'],
            ]);

            return false;
        }

        AiFaqDraf::create([
            'pertanyaan_perwakilan' => $perwakilan,
            'pertanyaan_hash' => $kelompok['hash'],
            'variasi' => $variasi,
            'frekuensi' => $kelompok['frekuensi'],
            'sumber' => $kelompok['sumber'],
            'status' => AiFaqDraf::STATUS_USULAN,
            'dibuat_periode' => now()->startOfMonth()->toDateString(),
        ]);

        return true;
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /** Teks FAQ untuk ingest RAG: "T: ... J: ..." + variasi pertanyaan. */
    private function renderTeksFaq(AiFaqDraf $draf): string
    {
        $teks = "T: {$draf->pertanyaan_perwakilan}\nJ: ".trim((string) $draf->jawaban);

        $variasi = array_values(array_filter((array) $draf->variasi, fn ($v) => trim((string) $v) !== ''));

        if ($variasi !== []) {
            $teks .= "\n\nVariasi pertanyaan serupa:\n- ".implode("\n- ", $variasi);
        }

        return $teks;
    }

    private function pastikanBoleh(User $user): void
    {
        if (! $user->can('ai.pengetahuan.kelola')) {
            throw new AuthorizationException(
                "Mohon maaf, Anda tidak memiliki izin 'ai.pengetahuan.kelola' untuk tindakan ini."
            );
        }
    }
}
