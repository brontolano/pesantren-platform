<?php

namespace Modules\AI\Services\Generator;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Modules\AI\Jobs\GenerateSoalJob;
use Modules\AI\Models\AiChunk;
use Modules\AI\Models\AiDrafSoal;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Rag\RetrievalResult;
use Modules\AI\Services\Rag\Retriever;

/**
 * T-A040 — Generator Soal & Kisi-kisi (PRD §8.1 M-AI-4, Blueprint §7).
 *
 * Alur: buatDraf (baris status antri + dispatch GenerateSoalJob queue 'ai',
 * async-by-default §7.3) → proses (per BATCH kecil: retrieval kurikulum →
 * generate via gateway agen asatidz_soal tier standar → parse JSON toleran →
 * lampirkan SITASI DETERMINISTIK per butir dari chunk retrieval) → status
 * draf → sahkan (HANYA manusia pemegang permission, pagar #3).
 *
 * Keputusan desain (vs RagAnswerer): generator memakai Retriever + AiGateway
 * LANGSUNG — CitationEnforcer & DisclaimerInjector RagAnswerer dirancang untuk
 * jawaban PROSA; output JSON terstruktur akan salah-verifikasi/tercemar
 * disclaimer. Pagar inti tetap utuh: RAG-only (retrieval kosong → gagal santun
 * TANPA HTTP), sitasi deterministik pipeline (model TIDAK pernah diminta
 * menulis sitasi), satu-satunya pintu LLM = AiGateway (Aturan Emas #4 —
 * policy/budget/metering/audit ikut aktif).
 *
 * Idempotensi: kunci metering per batch DETERMINISTIK
 * "{idempotency_key draf}:b{n}" — retry job tidak menggandakan kredit (dedup
 * ledger T-A002); butir SELALU ditulis ulang utuh (bukan append) — re-run
 * tidak menggandakan butir; draf berstatus final (draf/disahkan) di-skip.
 */
class SoalGenerator
{
    public const AGENT = 'asatidz_soal';

    /** Hard cap jumlah butir per draf — config max_jumlah tak boleh melewatinya. */
    private const HARD_CAP = 30;

    public function __construct(
        private readonly Retriever $retriever,
        private readonly AiGateway $gateway,
    ) {}

    // ------------------------------------------------------------------
    // Pembuatan draf (dipanggil halaman Filament)
    // ------------------------------------------------------------------

    /**
     * Validasi input form → baris ai_draf_soal status antri → dispatch job.
     *
     * @param  array{mapel?: string, source_id?: int|string|null, bab?: string|null, jenjang?: string|null, jumlah?: int|string|null, tipe?: string, tingkat_kognitif?: string|null}  $input
     *
     * @throws \InvalidArgumentException input tidak valid (pesan santun untuk UI)
     */
    public function buatDraf(array $input, ?User $user = null): AiDrafSoal
    {
        $mapel = trim((string) ($input['mapel'] ?? ''));

        if ($mapel === '') {
            throw new \InvalidArgumentException('Mohon isi nama mapel/kitab terlebih dahulu.');
        }

        $tipe = (string) ($input['tipe'] ?? 'pilihan_ganda');

        if (! in_array($tipe, AiDrafSoal::TIPE, true)) {
            throw new \InvalidArgumentException("Tipe soal tidak dikenal: '{$tipe}'.");
        }

        $tingkat = trim((string) ($input['tingkat_kognitif'] ?? ''));

        if ($tingkat !== '' && ! in_array($tingkat, AiDrafSoal::TINGKAT_KOGNITIF, true)) {
            throw new \InvalidArgumentException("Tingkat kognitif tidak dikenal: '{$tingkat}'.");
        }

        $sourceId = isset($input['source_id']) && $input['source_id'] !== null && $input['source_id'] !== ''
            ? (int) $input['source_id'] : null;

        if ($sourceId !== null && ! AiKnowledgeSource::query()->whereKey($sourceId)->exists()) {
            throw new \InvalidArgumentException('Sumber pengetahuan yang dipilih tidak ditemukan.');
        }

        $bab = trim((string) ($input['bab'] ?? ''));
        $jenjang = trim((string) ($input['jenjang'] ?? ''));

        $draf = AiDrafSoal::create([
            'judul' => 'Soal '.$mapel.($bab !== '' ? ' — '.$bab : ''),
            'mapel' => $mapel,
            'source_id' => $sourceId,
            'bab' => $bab !== '' ? $bab : null,
            'jenjang' => $jenjang !== '' ? $jenjang : null,
            'jumlah' => $this->jumlahTerjepit($input['jumlah'] ?? null),
            'tipe' => $tipe,
            'tingkat_kognitif' => $tingkat !== '' ? $tingkat : null,
            'status' => AiDrafSoal::STATUS_ANTRI,
            'butir' => [],
            'dibuat_oleh' => $user?->getKey(),
            'idempotency_key' => 'soal-'.Str::ulid(),
        ]);

        GenerateSoalJob::dispatch(
            (int) $draf->getKey(),
            tenancy()->initialized ? (string) tenant()?->getTenantKey() : null,
        );

        return $draf;
    }

    /** Jepit jumlah ke [1, min(config max_jumlah, HARD_CAP)]; kosong = default config. */
    public function jumlahTerjepit(int|string|null $jumlah): int
    {
        $cap = min(max(1, (int) config('ai.generator.soal.max_jumlah', 30)), self::HARD_CAP);
        $nilai = $jumlah === null || $jumlah === ''
            ? (int) config('ai.generator.soal.default_jumlah', 10)
            : (int) $jumlah;

        return max(1, min($nilai, $cap));
    }

    // ------------------------------------------------------------------
    // Pemrosesan (dipanggil GenerateSoalJob di queue 'ai')
    // ------------------------------------------------------------------

    /**
     * Susun butir per BATCH kecil (Blueprint §7.4: konteks ramping model 3B).
     * Kegagalan BISNIS (tanpa korpus / degradasi / format invalid) ditangani
     * anggun → status gagal + pesan santun (TIDAK melempar); exception hanya
     * untuk kegagalan teknis tak terduga (pemanggil/job yang menandai gagal).
     */
    public function proses(int $drafId): AiDrafSoal
    {
        /** @var AiDrafSoal $draf */
        $draf = AiDrafSoal::query()->findOrFail($drafId);

        // Idempoten: sudah final → no-op (re-dispatch/retry tidak mengulang HTTP).
        if (in_array($draf->status, [AiDrafSoal::STATUS_DRAF, AiDrafSoal::STATUS_DISAHKAN], true)) {
            return $draf;
        }

        $draf->update(['status' => AiDrafSoal::STATUS_MENYUSUN, 'error_ringkas' => null, 'butir' => []]);

        $batchSize = max(1, (int) config('ai.generator.soal.batch_size', 5));
        $totalBatch = (int) ceil($draf->jumlah / $batchSize);
        $semua = [];

        for ($b = 1; $b <= $totalBatch; $b++) {
            $target = min($batchSize, $draf->jumlah - count($semua));

            if ($target <= 0) {
                break;
            }

            // RAG-only (pagar #2): retrieval per batch. Kosong → gagal santun;
            // Retriever sendiri berhenti SEBELUM embed bila korpus kosong —
            // jalur ini TIDAK memicu HTTP apa pun ke provider.
            $retrieval = $this->retriever->cari($this->queryRetrieval($draf), $this->opsiRetrieval($draf));

            if (! $retrieval->adaSumber) {
                return $this->gagalkan($draf, (string) config(
                    'ai.generator.soal.pesan.tanpa_korpus',
                    'Mohon maaf, belum ada materi kurikulum yang dapat dirujuk untuk menyusun soal. Silakan unggah materi kurikulum terlebih dahulu di menu Sumber Pengetahuan, lalu coba kembali.',
                ));
            }

            $konteks = $this->susunKonteks($retrieval);
            $kunci = $draf->idempotency_key.':b'.$b;

            $hasil = $this->gateway->generate($this->requestBatch(
                $draf,
                prompt: $this->promptBatch($draf, $target, count($semua) + 1),
                konteks: $konteks,
                kunci: $kunci,
            ));

            if ($hasil->degraded || $hasil->ditolakPolicy) {
                return $this->gagalkan($draf, $hasil->text);
            }

            $butir = $this->parseButir($hasil->text, $target);

            // Parse gagal → retry parse-prompt 1× (kunci ':r1' — outcome
            // berbeda, TIDAK boleh ter-dedup ke batch asli) → masih gagal →
            // status gagal dengan pesan rapi.
            if ($butir === null) {
                $hasil = $this->gateway->generate($this->requestBatch(
                    $draf,
                    prompt: $this->promptPerbaikanFormat($draf, $target),
                    konteks: $konteks,
                    kunci: $kunci.':r1',
                ));

                if ($hasil->degraded || $hasil->ditolakPolicy) {
                    return $this->gagalkan($draf, $hasil->text);
                }

                $butir = $this->parseButir($hasil->text, $target);

                if ($butir === null) {
                    return $this->gagalkan($draf, (string) config(
                        'ai.generator.soal.pesan.format_invalid',
                        'Mohon maaf, asisten tidak menghasilkan format soal yang valid setelah dicoba ulang. Silakan coba beberapa saat lagi.',
                    ));
                }
            }

            // SITASI DETERMINISTIK (Blueprint §7.2): dilampirkan PIPELINE dari
            // chunk retrieval batch ini — model tidak pernah menulis sitasi.
            $sitasi = $this->sitasiDeterministik($retrieval);

            foreach ($butir as $item) {
                $semua[] = [
                    'no' => count($semua) + 1,
                    'pertanyaan' => $item['pertanyaan'],
                    'opsi' => $item['opsi'],
                    'kunci' => $item['kunci'],
                    'kisi' => $item['kisi'],
                    'sitasi' => $sitasi,
                ];
            }

            // Progress terlihat saat UI polling (status tetap 'menyusun').
            $draf->update(['butir' => $semua]);
        }

        if ($semua === []) {
            return $this->gagalkan($draf, (string) config('ai.generator.soal.pesan.format_invalid'));
        }

        $draf->update(['status' => AiDrafSoal::STATUS_DRAF, 'butir' => $semua]);

        return $draf->refresh();
    }

    // ------------------------------------------------------------------
    // Aksi manusia (pagar #3: human-in-the-loop)
    // ------------------------------------------------------------------

    /**
     * Pengesahan draf — HANYA pemegang permission ai.generator.soal; jejak
     * identitas + waktu tercatat. PRD T-A040: tidak ada soal masuk bank
     * tanpa pengesahan guru.
     *
     * @throws AuthorizationException|\InvalidArgumentException
     */
    public function sahkan(AiDrafSoal $draf, User $user): AiDrafSoal
    {
        if (! $user->can('ai.generator.soal')) {
            throw new AuthorizationException('Mohon maaf, akun Anda tidak memiliki izin untuk mengesahkan draf soal.');
        }

        if ($draf->status !== AiDrafSoal::STATUS_DRAF) {
            throw new \InvalidArgumentException('Hanya paket berstatus "draf" yang dapat disahkan.');
        }

        $draf->update([
            'status' => AiDrafSoal::STATUS_DISAHKAN,
            'disahkan_oleh' => (int) $user->getKey(),
            'disahkan_pada' => now(),
        ]);

        return $draf->refresh();
    }

    /**
     * Edit butir per nomor (pertanyaan/opsi/kunci/kisi) — hanya saat status
     * draf. SITASI & nomor TIDAK dapat diedit (deterministik pipeline);
     * payload sitasi dari editor diabaikan.
     *
     * @param  list<array{no?: int, pertanyaan?: string, opsi?: list<string>|null, kunci?: string, kisi?: string}>  $perubahan
     *
     * @throws \InvalidArgumentException
     */
    public function perbaruiButir(AiDrafSoal $draf, array $perubahan): AiDrafSoal
    {
        if ($draf->status !== AiDrafSoal::STATUS_DRAF) {
            throw new \InvalidArgumentException('Butir hanya dapat diedit selama paket berstatus "draf".');
        }

        $editPerNo = [];

        foreach ($perubahan as $edit) {
            if (is_array($edit) && isset($edit['no'])) {
                $editPerNo[(int) $edit['no']] = $edit;
            }
        }

        $hasil = [];

        foreach ((array) $draf->butir as $item) {
            $edit = $editPerNo[(int) ($item['no'] ?? 0)] ?? null;

            if ($edit !== null) {
                $item['pertanyaan'] = trim((string) ($edit['pertanyaan'] ?? $item['pertanyaan'] ?? ''));
                $item['kunci'] = trim((string) ($edit['kunci'] ?? $item['kunci'] ?? ''));
                $item['kisi'] = trim((string) ($edit['kisi'] ?? $item['kisi'] ?? ''));

                if (array_key_exists('opsi', $edit)) {
                    $item['opsi'] = is_array($edit['opsi'])
                        ? array_values(array_map(static fn ($o): string => trim((string) $o), $edit['opsi']))
                        : null;
                }
                // 'no' dan 'sitasi' SENGAJA tidak disentuh — jejak pipeline.
            }

            $hasil[] = $item;
        }

        $draf->update(['butir' => $hasil]);

        return $draf->refresh();
    }

    // ------------------------------------------------------------------
    // Retrieval & prompt
    // ------------------------------------------------------------------

    /** Kueri retrieval dari atribut draf (mapel + bab + jenjang). */
    private function queryRetrieval(AiDrafSoal $draf): string
    {
        return trim(implode(' ', array_filter([
            $draf->mapel,
            $draf->bab,
            $draf->jenjang,
            'materi pembelajaran',
        ])));
    }

    /**
     * Opsi retrieval: sumber pilihan pengguna menang (sumber itulah otoritas
     * materi — tanpa filter kategori); tanpa pilihan = kategori kurikulum.
     *
     * @return array{kategori?: string, sourceId?: int}
     */
    private function opsiRetrieval(AiDrafSoal $draf): array
    {
        return $draf->source_id !== null
            ? ['sourceId' => (int) $draf->source_id]
            : ['kategori' => 'kurikulum'];
    }

    /**
     * Gabungan chunk dengan header sumber readable (pola RagAnswerer):
     * pembungkusan blok DATA berdelimiter diserahkan ke gateway (T-A005).
     */
    private function susunKonteks(RetrievalResult $retrieval): string
    {
        $bagian = [];

        foreach ($retrieval->chunks as $i => $item) {
            /** @var AiChunk $chunk */
            $chunk = $item['chunk'];
            /** @var AiKnowledgeSource $sumber */
            $sumber = $item['sumber'];

            $bagian[] = '[Sumber '.($i + 1).': '.$this->labelSitasi(
                (string) $sumber->judul,
                isset($chunk->metadata['bab']) ? (string) $chunk->metadata['bab'] : null,
                isset($chunk->metadata['halaman']) ? (int) $chunk->metadata['halaman'] : null,
            )."]\n".$chunk->konten;
        }

        return implode("\n\n", $bagian);
    }

    /**
     * Daftar sitasi deterministik dari chunk retrieval terpakai batch ini —
     * bentuk sama dengan AiGenerateResult::sitasi (kompatibel blok-sitasi UI).
     *
     * @return list<array{judul: string, bab: string|null, halaman: int|null, label: string}>
     */
    private function sitasiDeterministik(RetrievalResult $retrieval): array
    {
        $sitasi = [];

        foreach ($retrieval->chunks as $item) {
            /** @var AiChunk $chunk */
            $chunk = $item['chunk'];
            /** @var AiKnowledgeSource $sumber */
            $sumber = $item['sumber'];

            $bab = isset($chunk->metadata['bab']) ? (string) $chunk->metadata['bab'] : null;
            $halaman = isset($chunk->metadata['halaman']) ? (int) $chunk->metadata['halaman'] : null;

            $sitasi[] = [
                'judul' => (string) $sumber->judul,
                'bab' => $bab,
                'halaman' => $halaman,
                'label' => $this->labelSitasi((string) $sumber->judul, $bab, $halaman),
            ];
        }

        return $sitasi;
    }

    /** Format readable "Judul — bab, hlm. N" (bagian kosong dilewati). */
    private function labelSitasi(string $judul, ?string $bab, ?int $halaman): string
    {
        $detail = array_filter([
            $bab,
            $halaman !== null ? 'hlm. '.$halaman : null,
        ]);

        return $detail === [] ? $judul : $judul.' — '.implode(', ', $detail);
    }

    private function requestBatch(AiDrafSoal $draf, string $prompt, string $konteks, string $kunci): AiGenerateRequest
    {
        return (new AiGenerateRequest(
            prompt: $prompt,
            agent: self::AGENT,
            tier: 'standar',
            channel: 'web',
            userId: $draf->dibuat_oleh,
            idempotencyKey: $kunci,
            ringkasanKonteks: 'generator_soal: '.$draf->judul,
        ))->withContext($konteks);
    }

    /**
     * Prompt batch — instruksi TEKNIS netral (tanpa instruksi sitasi: sitasi
     * urusan pipeline; tanpa konten keagamaan: Aturan Emas #8).
     */
    private function promptBatch(AiDrafSoal $draf, int $target, int $nomorAwal): string
    {
        $jenis = match ($draf->tipe) {
            'esai' => 'soal esai (tanpa pilihan jawaban; "opsi" diisi null)',
            'campuran' => 'campuran soal pilihan ganda (4 pilihan) dan soal esai',
            default => 'soal pilihan ganda dengan 4 pilihan jawaban',
        };

        $bagian = [
            "Susun {$target} butir {$jenis} dari materi pada konteks berikut,",
            "untuk mapel/kitab \"{$draf->mapel}\"",
        ];

        if ($draf->bab !== null) {
            $bagian[] = "bab \"{$draf->bab}\"";
        }

        if ($draf->jenjang !== null) {
            $bagian[] = "jenjang {$draf->jenjang}";
        }

        if ($draf->tingkat_kognitif !== null) {
            $bagian[] = "dengan tingkat kognitif {$draf->tingkat_kognitif}";
        }

        $bagian[] = "Beri nomor mulai dari {$nomorAwal}.";
        $bagian[] = 'Keluarkan HANYA JSON array sesuai format yang ditetapkan, tanpa teks lain.';

        return implode(' ', $bagian);
    }

    /** Prompt retry-parse: ulangi permintaan dengan penegasan format. */
    private function promptPerbaikanFormat(AiDrafSoal $draf, int $target): string
    {
        return 'Jawaban sebelumnya bukan JSON yang valid. '
            .$this->promptBatch($draf, $target, 1)
            .' Pastikan keluaran berupa JSON array murni: mulai dengan karakter "[" dan diakhiri "]", tanpa penjelasan apa pun.';
    }

    // ------------------------------------------------------------------
    // Parsing JSON toleran
    // ------------------------------------------------------------------

    /**
     * Parse keluaran model: coba beberapa kandidat blok JSON (teks utuh,
     * fenced code block, substring [..] / {..}); butir valid minimal punya
     * 'pertanyaan' non-kosong. null = tidak ada butir valid sama sekali.
     *
     * @return list<array{pertanyaan: string, opsi: list<string>|null, kunci: string, kisi: string}>|null
     */
    private function parseButir(string $teks, int $maks): ?array
    {
        foreach ($this->kandidatJson($teks) as $kandidat) {
            $data = json_decode($kandidat, true);

            if (! is_array($data)) {
                continue;
            }

            // Toleransi objek pembungkus {"butir": [...]} / {"soal": [...]}.
            foreach (['butir', 'soal', 'items', 'questions'] as $kunci) {
                if (isset($data[$kunci]) && is_array($data[$kunci])) {
                    $data = $data[$kunci];
                    break;
                }
            }

            $valid = [];

            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $pertanyaan = trim((string) ($item['pertanyaan'] ?? ''));

                if ($pertanyaan === '') {
                    continue;
                }

                $valid[] = [
                    'pertanyaan' => $pertanyaan,
                    'opsi' => isset($item['opsi']) && is_array($item['opsi'])
                        ? array_values(array_map(static fn ($o): string => trim((string) $o), $item['opsi']))
                        : null,
                    'kunci' => trim((string) ($item['kunci'] ?? '')),
                    'kisi' => trim((string) ($item['kisi'] ?? '')),
                ];
            }

            if ($valid !== []) {
                return array_slice($valid, 0, $maks);
            }
        }

        return null;
    }

    /** @return list<string> kandidat string JSON, paling spesifik dulu */
    private function kandidatJson(string $teks): array
    {
        $teks = trim($teks);
        $kandidat = [];

        if (preg_match('/```(?:json)?\s*(.+?)```/su', $teks, $m)) {
            $kandidat[] = trim($m[1]);
        }

        $kandidat[] = $teks;

        $a = strpos($teks, '[');
        $b = strrpos($teks, ']');

        if ($a !== false && $b !== false && $b > $a) {
            $kandidat[] = substr($teks, $a, $b - $a + 1);
        }

        $a = strpos($teks, '{');
        $b = strrpos($teks, '}');

        if ($a !== false && $b !== false && $b > $a) {
            $objek = substr($teks, $a, $b - $a + 1);
            $kandidat[] = $objek;          // {"butir": [...]}
            $kandidat[] = '['.$objek.']';  // satu objek butir telanjang
        }

        return array_values(array_unique($kandidat));
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function gagalkan(AiDrafSoal $draf, string $pesan): AiDrafSoal
    {
        $draf->update([
            'status' => AiDrafSoal::STATUS_GAGAL,
            'error_ringkas' => mb_substr($pesan, 0, 500),
        ]);

        return $draf->refresh();
    }
}
