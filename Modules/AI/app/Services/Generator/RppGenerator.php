<?php

namespace Modules\AI\Services\Generator;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Modules\AI\Jobs\GenerateRppJob;
use Modules\AI\Models\AiChunk;
use Modules\AI\Models\AiDrafRpp;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Models\AiTemplateRpp;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Rag\RetrievalResult;
use Modules\AI\Services\Rag\Retriever;

/**
 * T-A041 — Generator RPP / Modul Ajar (PRD §8.1 M-AI-4) — KEMBARAN T-A040
 * (SoalGenerator): buatDraf (baris status antri + dispatch GenerateRppJob
 * queue 'ai', async-by-default §7.3) → proses (per SEKSI template satu
 * panggilan LLM dengan konteks ramping: retrieval top-3 + petunjuk seksi +
 * isian awal guru seksi itu) → status draf → sahkan (HANYA manusia pemegang
 * permission, pagar #3).
 *
 * ADAPTASI SADAR vs PRD: taksonomi KMA 939/2024 / KMA 1495/2025 TIDAK
 * di-hardcode (Aturan Emas #11 + #8 — regulasi adalah konten yang harus
 * ditinjau manusia). Struktur dokumen hidup di ai_template_rpp MILIK TENANT;
 * platform hanya menyediakan satu template default generik netral
 * (AiTemplateRppSeeder) yang bisa diubah penuh oleh Wakakurikulum.
 *
 * Hemat panggilan: seksi yang petunjuknya memuat kata "salin" (mis. seksi
 * identitas) TIDAK pernah lewat LLM — kontennya disalin verbatim dari isian
 * guru (kosong pun dibiarkan kosong untuk diisi guru di editor; LLM yang
 * mengarang identitas justru berbahaya).
 *
 * Pagar inti sama dengan SoalGenerator: RAG-only (retrieval kosong → gagal
 * santun TANPA HTTP), sitasi deterministik pipeline per seksi (model TIDAK
 * pernah diminta menulis sitasi), satu-satunya pintu LLM = AiGateway
 * (Aturan Emas #4 — policy/budget/metering/audit ikut aktif).
 *
 * Idempotensi: kunci metering per seksi DETERMINISTIK
 * "{idempotency_key draf}:s{kunci-seksi}" — retry job tidak menggandakan
 * kredit (dedup ledger T-A002); isi SELALU ditulis ulang utuh (bukan append);
 * draf berstatus final (draf/disahkan) di-skip.
 */
class RppGenerator
{
    public const AGENT = 'asatidz_rpp';

    /** Hard cap jumlah seksi per template (jaga durasi job & biaya). */
    public const MAX_SEKSI = 20;

    public function __construct(
        private readonly Retriever $retriever,
        private readonly AiGateway $gateway,
    ) {}

    // ------------------------------------------------------------------
    // Template (CRUD — dipakai halaman Filament, gating permission di page)
    // ------------------------------------------------------------------

    /**
     * Validasi + simpan template (buat baru bila $template null).
     *
     * @param  array{nama?: string, jenis_lembaga?: string|null, aktif?: bool, seksi?: list<array{kunci?: string, judul?: string, petunjuk?: string, wajib?: bool}>}  $input
     *
     * @throws \InvalidArgumentException input tidak valid (pesan santun untuk UI)
     */
    public function simpanTemplate(array $input, ?User $user = null, ?AiTemplateRpp $template = null): AiTemplateRpp
    {
        $nama = trim((string) ($input['nama'] ?? ''));

        if ($nama === '') {
            throw new \InvalidArgumentException('Mohon isi nama template terlebih dahulu.');
        }

        $seksi = $this->normalisasiSeksi((array) ($input['seksi'] ?? []));

        $jenis = trim((string) ($input['jenis_lembaga'] ?? ''));

        $data = [
            'nama' => $nama,
            'jenis_lembaga' => $jenis !== '' ? $jenis : null,
            'seksi' => $seksi,
            'aktif' => (bool) ($input['aktif'] ?? true),
        ];

        if ($template === null) {
            return AiTemplateRpp::create($data + ['dibuat_oleh' => $user?->getKey()]);
        }

        $template->update($data);

        return $template->refresh();
    }

    /**
     * Hapus template — ditolak santun bila sudah dipakai draf (FK restrict);
     * matikan lewat kolom `aktif` sebagai gantinya.
     *
     * @throws \InvalidArgumentException
     */
    public function hapusTemplate(AiTemplateRpp $template): void
    {
        if ($template->draf()->exists()) {
            throw new \InvalidArgumentException(
                'Template ini sudah dipakai oleh draf RPP sehingga tidak dapat dihapus. '
                .'Silakan nonaktifkan template bila tidak ingin dipakai lagi.',
            );
        }

        $template->delete();
    }

    /**
     * Validasi struktur seksi: judul wajib, kunci unik (auto-slug dari judul
     * bila kosong), wajib bool; jumlah dijepit MAX_SEKSI.
     *
     * @param  list<array<string, mixed>>  $mentah
     * @return list<array{kunci: string, judul: string, petunjuk: string, wajib: bool}>
     *
     * @throws \InvalidArgumentException
     */
    private function normalisasiSeksi(array $mentah): array
    {
        $hasil = [];
        $terpakai = [];

        foreach ($mentah as $baris) {
            if (! is_array($baris)) {
                continue;
            }

            $judul = trim((string) ($baris['judul'] ?? ''));

            if ($judul === '') {
                continue; // baris kosong dari editor diabaikan diam-diam
            }

            $kunci = Str::slug(trim((string) ($baris['kunci'] ?? '')) ?: $judul, '_');

            if ($kunci === '') {
                throw new \InvalidArgumentException("Kunci seksi untuk \"{$judul}\" tidak valid.");
            }

            if (isset($terpakai[$kunci])) {
                throw new \InvalidArgumentException("Kunci seksi '{$kunci}' dipakai lebih dari satu kali — mohon gunakan kunci yang berbeda.");
            }

            $terpakai[$kunci] = true;

            $hasil[] = [
                'kunci' => $kunci,
                'judul' => $judul,
                'petunjuk' => trim((string) ($baris['petunjuk'] ?? '')),
                'wajib' => (bool) ($baris['wajib'] ?? false),
            ];
        }

        if ($hasil === []) {
            throw new \InvalidArgumentException('Template membutuhkan minimal satu seksi dengan judul.');
        }

        if (count($hasil) > self::MAX_SEKSI) {
            throw new \InvalidArgumentException('Jumlah seksi template maksimal '.self::MAX_SEKSI.'.');
        }

        return $hasil;
    }

    /** Seksi "salin": petunjuk memuat kata "salin" → konten dari isian guru, TANPA LLM. */
    public static function seksiSalin(array $seksi): bool
    {
        return (bool) preg_match('/\bsalin\b/iu', (string) ($seksi['petunjuk'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Pembuatan draf (dipanggil halaman Filament)
    // ------------------------------------------------------------------

    /**
     * Validasi input form → baris ai_draf_rpp status antri → dispatch job.
     *
     * @param  array{template_id?: int|string|null, mapel?: string, kelas?: string|null, source_id?: int|string|null, input_guru?: array<string, string>|null}  $input
     *
     * @throws \InvalidArgumentException input tidak valid (pesan santun untuk UI)
     */
    public function buatDraf(array $input, ?User $user = null): AiDrafRpp
    {
        $templateId = isset($input['template_id']) && $input['template_id'] !== null && $input['template_id'] !== ''
            ? (int) $input['template_id'] : null;

        /** @var AiTemplateRpp|null $template */
        $template = $templateId !== null
            ? AiTemplateRpp::query()->whereKey($templateId)->where('aktif', true)->first()
            : null;

        if ($template === null) {
            throw new \InvalidArgumentException('Mohon pilih template RPP yang aktif terlebih dahulu.');
        }

        $mapel = trim((string) ($input['mapel'] ?? ''));

        if ($mapel === '') {
            throw new \InvalidArgumentException('Mohon isi nama mapel/kitab terlebih dahulu.');
        }

        $sourceId = isset($input['source_id']) && $input['source_id'] !== null && $input['source_id'] !== ''
            ? (int) $input['source_id'] : null;

        if ($sourceId !== null && ! AiKnowledgeSource::query()->whereKey($sourceId)->exists()) {
            throw new \InvalidArgumentException('Sumber pengetahuan yang dipilih tidak ditemukan.');
        }

        $kelas = trim((string) ($input['kelas'] ?? ''));

        $draf = AiDrafRpp::create([
            'template_id' => (int) $template->getKey(),
            'judul' => 'RPP '.$mapel.($kelas !== '' ? ' — '.$kelas : ''),
            'mapel' => $mapel,
            'kelas' => $kelas !== '' ? $kelas : null,
            'source_id' => $sourceId,
            'input_guru' => $this->saringInputGuru($template, (array) ($input['input_guru'] ?? [])),
            'status' => AiDrafRpp::STATUS_ANTRI,
            'isi' => [],
            'dibuat_oleh' => $user?->getKey(),
            'idempotency_key' => 'rpp-'.Str::ulid(),
        ]);

        GenerateRppJob::dispatch(
            (int) $draf->getKey(),
            tenancy()->initialized ? (string) tenant()?->getTenantKey() : null,
        );

        return $draf;
    }

    /**
     * Hanya isian untuk kunci seksi yang DIKENAL template, non-kosong.
     *
     * @param  array<string, mixed>  $mentah
     * @return array<string, string>
     */
    private function saringInputGuru(AiTemplateRpp $template, array $mentah): array
    {
        $kunciSah = array_column((array) $template->seksi, 'kunci');
        $hasil = [];

        foreach ($mentah as $kunci => $teks) {
            $teks = trim((string) $teks);

            if ($teks !== '' && in_array((string) $kunci, $kunciSah, true)) {
                $hasil[(string) $kunci] = $teks;
            }
        }

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Pemrosesan (dipanggil GenerateRppJob di queue 'ai')
    // ------------------------------------------------------------------

    /**
     * Isi dokumen per SEKSI template — satu panggilan LLM per seksi dengan
     * konteks ramping (Blueprint §7.4: retrieval top-3 + petunjuk seksi +
     * isian awal guru seksi itu). Seksi "salin" tidak pernah lewat LLM.
     * Kegagalan BISNIS (tanpa korpus / degradasi / konten kosong) ditangani
     * anggun → status gagal + pesan santun (TIDAK melempar); exception hanya
     * untuk kegagalan teknis tak terduga (pemanggil/job yang menandai gagal).
     */
    public function proses(int $drafId): AiDrafRpp
    {
        /** @var AiDrafRpp $draf */
        $draf = AiDrafRpp::query()->findOrFail($drafId);

        // Idempoten: sudah final → no-op (re-dispatch/retry tidak mengulang HTTP).
        if (in_array($draf->status, [AiDrafRpp::STATUS_DRAF, AiDrafRpp::STATUS_DISAHKAN], true)) {
            return $draf;
        }

        $draf->update(['status' => AiDrafRpp::STATUS_MENYUSUN, 'error_ringkas' => null, 'isi' => []]);

        $template = $draf->template;
        $daftarSeksi = $template !== null ? array_slice((array) $template->seksi, 0, self::MAX_SEKSI) : [];

        if ($daftarSeksi === []) {
            return $this->gagalkan($draf, 'Mohon maaf, template RPP draf ini tidak ditemukan atau tidak memiliki seksi. Silakan periksa templatenya lalu buat draf baru.');
        }

        $inputGuru = (array) $draf->input_guru;
        $isi = [];

        foreach ($daftarSeksi as $seksi) {
            $kunci = (string) ($seksi['kunci'] ?? '');
            $judul = (string) ($seksi['judul'] ?? $kunci);
            $catatanGuru = trim((string) ($inputGuru[$kunci] ?? ''));

            // Seksi "salin" (mis. identitas): konten = isian guru verbatim,
            // TANPA LLM & TANPA retrieval — deterministik + hemat kredit.
            if (self::seksiSalin($seksi)) {
                $isi[] = ['kunci' => $kunci, 'judul' => $judul, 'konten' => $catatanGuru, 'sitasi' => []];
                $draf->update(['isi' => $isi]);

                continue;
            }

            // RAG-only (pagar #2): retrieval per seksi. Kosong → gagal santun;
            // Retriever berhenti SEBELUM embed bila korpus kosong — jalur ini
            // TIDAK memicu HTTP apa pun ke provider.
            $retrieval = $this->retriever->cari($this->queryRetrieval($draf, $judul), $this->opsiRetrieval($draf));

            if (! $retrieval->adaSumber) {
                return $this->gagalkan($draf, (string) config(
                    'ai.generator.rpp.pesan.tanpa_korpus',
                    'Mohon maaf, belum ada materi kurikulum yang dapat dirujuk untuk menyusun RPP. Silakan unggah materi kurikulum terlebih dahulu di menu Sumber Pengetahuan, lalu coba kembali.',
                ));
            }

            $hasil = $this->gateway->generate($this->requestSeksi(
                $draf,
                prompt: $this->promptSeksi($draf, $seksi, $catatanGuru),
                konteks: $this->susunKonteks($retrieval),
                kunci: $draf->idempotency_key.':s'.$kunci, // idempotensi per (draf, seksi)
            ));

            if ($hasil->degraded || $hasil->ditolakPolicy) {
                return $this->gagalkan($draf, $hasil->text);
            }

            $konten = trim($hasil->text);

            if ($konten === '') {
                return $this->gagalkan($draf, (string) config(
                    'ai.generator.rpp.pesan.konten_kosong',
                    'Mohon maaf, asisten tidak menghasilkan isi untuk salah satu seksi RPP. Silakan coba beberapa saat lagi.',
                ));
            }

            // SITASI DETERMINISTIK per seksi (Blueprint §7.2): dilampirkan
            // PIPELINE dari chunk retrieval seksi ini — bukan dari model.
            $isi[] = [
                'kunci' => $kunci,
                'judul' => $judul,
                'konten' => $konten,
                'sitasi' => $this->sitasiDeterministik($retrieval),
            ];

            // Progress terlihat saat UI polling (status tetap 'menyusun').
            $draf->update(['isi' => $isi]);
        }

        $draf->update(['status' => AiDrafRpp::STATUS_DRAF, 'isi' => $isi]);

        return $draf->refresh();
    }

    // ------------------------------------------------------------------
    // Aksi manusia (pagar #3: human-in-the-loop)
    // ------------------------------------------------------------------

    /**
     * Pengesahan draf — HANYA pemegang permission ai.generator.rpp; jejak
     * identitas + waktu tercatat. PRD T-A041: dokumen selalu lahir DRAF.
     *
     * @throws AuthorizationException|\InvalidArgumentException
     */
    public function sahkan(AiDrafRpp $draf, User $user): AiDrafRpp
    {
        if (! $user->can('ai.generator.rpp')) {
            throw new AuthorizationException('Mohon maaf, akun Anda tidak memiliki izin untuk mengesahkan draf RPP.');
        }

        if ($draf->status !== AiDrafRpp::STATUS_DRAF) {
            throw new \InvalidArgumentException('Hanya dokumen berstatus "draf" yang dapat disahkan.');
        }

        $draf->update([
            'status' => AiDrafRpp::STATUS_DISAHKAN,
            'disahkan_oleh' => (int) $user->getKey(),
            'disahkan_pada' => now(),
        ]);

        return $draf->refresh();
    }

    /**
     * Edit konten per seksi (kunci) — hanya saat status draf. SITASI, kunci,
     * dan judul seksi TIDAK dapat diedit (jejak deterministik pipeline);
     * payload selain 'konten' dari editor diabaikan.
     *
     * @param  list<array{kunci?: string, konten?: string}>  $perubahan
     *
     * @throws \InvalidArgumentException
     */
    public function perbaruiIsi(AiDrafRpp $draf, array $perubahan): AiDrafRpp
    {
        if ($draf->status !== AiDrafRpp::STATUS_DRAF) {
            throw new \InvalidArgumentException('Isi hanya dapat diedit selama dokumen berstatus "draf".');
        }

        $editPerKunci = [];

        foreach ($perubahan as $edit) {
            if (is_array($edit) && isset($edit['kunci'])) {
                $editPerKunci[(string) $edit['kunci']] = $edit;
            }
        }

        $hasil = [];

        foreach ((array) $draf->isi as $seksi) {
            $edit = $editPerKunci[(string) ($seksi['kunci'] ?? '')] ?? null;

            if ($edit !== null && array_key_exists('konten', $edit)) {
                $seksi['konten'] = trim((string) $edit['konten']);
                // 'kunci', 'judul', dan 'sitasi' SENGAJA tidak disentuh — jejak pipeline.
            }

            $hasil[] = $seksi;
        }

        $draf->update(['isi' => $hasil]);

        return $draf->refresh();
    }

    // ------------------------------------------------------------------
    // Retrieval & prompt
    // ------------------------------------------------------------------

    /** Kueri retrieval per seksi (mapel + kelas + judul seksi). */
    private function queryRetrieval(AiDrafRpp $draf, string $judulSeksi): string
    {
        return trim(implode(' ', array_filter([
            $draf->mapel,
            $draf->kelas,
            $judulSeksi,
            'materi pembelajaran',
        ])));
    }

    /**
     * Opsi retrieval: sumber pilihan pengguna menang (sumber itulah otoritas
     * materi — tanpa filter kategori); tanpa pilihan = kategori kurikulum.
     * topK ramping (default 3) — konteks per seksi tetap kecil (§7.4).
     *
     * @return array{kategori?: string, sourceId?: int, topK: int}
     */
    private function opsiRetrieval(AiDrafRpp $draf): array
    {
        $topK = max(1, (int) config('ai.generator.rpp.top_k', 3));

        return $draf->source_id !== null
            ? ['sourceId' => (int) $draf->source_id, 'topK' => $topK]
            : ['kategori' => 'kurikulum', 'topK' => $topK];
    }

    /**
     * Gabungan chunk dengan header sumber readable (pola SoalGenerator):
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
     * Daftar sitasi deterministik dari chunk retrieval terpakai seksi ini —
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

    private function requestSeksi(AiDrafRpp $draf, string $prompt, string $konteks, string $kunci): AiGenerateRequest
    {
        return (new AiGenerateRequest(
            prompt: $prompt,
            agent: self::AGENT,
            tier: 'standar',
            channel: 'web',
            userId: $draf->dibuat_oleh,
            idempotencyKey: $kunci,
            ringkasanKonteks: 'generator_rpp: '.$draf->judul,
        ))->withContext($konteks);
    }

    /**
     * Prompt per seksi — instruksi TEKNIS-PEDAGOGIS netral (tanpa instruksi
     * sitasi: sitasi urusan pipeline; tanpa konten regulasi/keagamaan:
     * Aturan Emas #8/#11 — petunjuk pengisian datang dari template tenant).
     */
    private function promptSeksi(AiDrafRpp $draf, array $seksi, string $catatanGuru): string
    {
        $bagian = [
            'Isi SATU seksi dokumen RPP/modul ajar berjudul "'.(string) ($seksi['judul'] ?? '').'"',
            "untuk mapel/kitab \"{$draf->mapel}\"",
        ];

        if ($draf->kelas !== null) {
            $bagian[] = "kelas/jenjang {$draf->kelas}";
        }

        $petunjuk = trim((string) ($seksi['petunjuk'] ?? ''));

        if ($petunjuk !== '') {
            $bagian[] = "Petunjuk pengisian seksi: {$petunjuk}";
        }

        if ($catatanGuru !== '') {
            $bagian[] = "Catatan awal dari guru (kembangkan, jangan abaikan): {$catatanGuru}";
        }

        $bagian[] = 'Susun isi seksi HANYA dari materi pada konteks.';
        $bagian[] = 'Jawab dalam bahasa Indonesia formal sebagai teks polos tanpa format markdown, langsung isi seksinya tanpa kalimat pembuka maupun penutup.';

        return implode('. ', $bagian);
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function gagalkan(AiDrafRpp $draf, string $pesan): AiDrafRpp
    {
        $draf->update([
            'status' => AiDrafRpp::STATUS_GAGAL,
            'error_ringkas' => mb_substr($pesan, 0, 500),
        ]);

        return $draf->refresh();
    }
}
