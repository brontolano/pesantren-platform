<?php

namespace Modules\AI\Services\Generator;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Modules\AI\Jobs\GenerateIkhtisarJob;
use Modules\AI\Models\AiChunk;
use Modules\AI\Models\AiDrafIkhtisar;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Guardrails\CitationEnforcer;
use Modules\AI\Services\Guardrails\DisclaimerInjector;
use Modules\AI\Services\Rag\RetrievalResult;
use Modules\AI\Services\Rag\Retriever;
use Modules\AI\Services\Settings\TenantSettingService;

/**
 * T-A042 — Ikhtisar materi kitab bersitasi & generator kultum (PRD §8.1) —
 * SATU service untuk DUA jenis dokumen, kembaran pola SoalGenerator (T-A040):
 * buatDraf (baris antri + dispatch GenerateIkhtisarJob queue 'ai',
 * async-by-default §7.3) → proses (retrieval dari sumber terpilih → SATU
 * panggilan LLM → parse JSON toleran + retry format 1× → sitasi DETERMINISTIK
 * pipeline) → status draf → sahkan (HANYA manusia pemegang permission,
 * pagar #3).
 *
 * ADAPTASI SADAR vs PRD (dicatat di PR): "whitelist kitab" GATE-3 belum
 * terisi → sumber v1 = Sumber Pengetahuan TENANT (ai_knowledge_source),
 * konsisten keputusan owner 2026-06-11 (kitab per pesantren). Saat whitelist
 * GATE-3 terisi + korpus shared T-A032 ada, sumber tinggal bertambah.
 *
 * KULTUM = fitur OPT-IN (Q8 PRD merekomendasikan opt-in; belum dikonfirmasi
 * owner) — default OFF di AiTenantSettingDefaults; dua lapis penegakan:
 *  1. buatDraf menolak santun bila fitur belum aktif (form pun menyembunyikan
 *     opsinya);
 *  2. PolicyEngine cekFiturPerPeran (agent asatidz_kultum → fitur
 *     generator_kultum) menolak di gateway TANPA HTTP bila lapis 1 terlewati.
 * Output kultum = STRUKTUR kerangka saja: klaim dalil dinormalisasi pipeline
 * berawalan [PERIKSA DALIL], rujukan SELALU dipaksa kosong (model tak pernah
 * dipercaya menulis dalil — Aturan Emas: JANGAN mengarang ayat/hadits),
 * plus disclaimer kategori 'agama' PRD §7.5 (DisclaimerInjector — override
 * tenant tetap dihormati) + teks prosedural tambahan dari config.
 *
 * Sitasi: kolom `sitasi` draf = daftar sumber deterministik dari chunk
 * retrieval; tiap poin ikhtisar membawa `sitasi_idx` (indeks daftar itu) —
 * poin → sumber tertelusur (AC T-A042) tanpa menggandakan label per poin.
 *
 * Idempotensi: kunci metering DETERMINISTIK "{idempotency_key}:g1" (retry
 * parse ":g1:r1" — outcome berbeda, tak boleh ter-dedup) — retry job tidak
 * menggandakan kredit (dedup ledger T-A002); isi SELALU ditulis ulang utuh;
 * draf berstatus final (draf/disahkan) di-skip.
 */
class IkhtisarGenerator
{
    public const AGENT_IKHTISAR = 'asatidz_ikhtisar';

    public const AGENT_KULTUM = 'asatidz_kultum';

    /** Hard cap jumlah butir per bagian isi (jaga ukuran dokumen & UI). */
    private const MAX_BUTIR = 20;

    public function __construct(
        private readonly Retriever $retriever,
        private readonly AiGateway $gateway,
    ) {}

    /**
     * Fitur kultum aktif untuk tenant ini? Membaca matriks fitur_per_peran
     * versi pengaturan AKTIF (Wizard + approval kyai): true bila MINIMAL SATU
     * peran mengaktifkan 'generator_kultum'. Dipakai form (sembunyikan opsi)
     * dan buatDraf (tolak santun) — penegakan per-peran yang presisi tetap
     * di PolicyEngine saat generate.
     */
    public static function kultumAktif(): bool
    {
        $setting = app(TenantSettingService::class)->aktif();

        if ($setting === null) {
            return false; // fail-closed: tanpa pengaturan aktif = OFF
        }

        $matriks = (array) (((array) $setting->payload)['fitur_per_peran'] ?? []);

        foreach ($matriks as $fiturs) {
            if ((((array) $fiturs)['generator_kultum'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Pembuatan draf (dipanggil halaman Filament)
    // ------------------------------------------------------------------

    /**
     * Validasi input form → baris ai_draf_ikhtisar status antri → dispatch job.
     *
     * @param  array{jenis?: string, source_id?: int|string|null, bab?: string|null, tema?: string|null, durasi_menit?: int|string|null}  $input
     *
     * @throws \InvalidArgumentException input tidak valid (pesan santun untuk UI)
     */
    public function buatDraf(array $input, ?User $user = null): AiDrafIkhtisar
    {
        $jenis = (string) ($input['jenis'] ?? AiDrafIkhtisar::JENIS_IKHTISAR);

        if (! in_array($jenis, AiDrafIkhtisar::JENIS, true)) {
            throw new \InvalidArgumentException("Jenis dokumen tidak dikenal: '{$jenis}'.");
        }

        // KULTUM OPT-IN (lapis 1): fitur belum aktif → tolak santun. Lapis 2
        // (PolicyEngine per peran) tetap menjaga jalur gateway.
        if ($jenis === AiDrafIkhtisar::JENIS_KULTUM && ! self::kultumAktif()) {
            throw new \InvalidArgumentException((string) config(
                'ai.generator.ikhtisar.pesan.kultum_nonaktif',
                'Mohon maaf, fitur kerangka kultum belum diaktifkan untuk pesantren Anda. Silakan hubungi admin untuk mengaktifkannya melalui Pengaturan AI bila dibutuhkan.',
            ));
        }

        $sourceId = isset($input['source_id']) && $input['source_id'] !== null && $input['source_id'] !== ''
            ? (int) $input['source_id'] : null;

        /** @var AiKnowledgeSource|null $sumber */
        $sumber = $sourceId !== null ? AiKnowledgeSource::query()->find($sourceId) : null;

        if ($sourceId !== null && $sumber === null) {
            throw new \InvalidArgumentException('Sumber pengetahuan yang dipilih tidak ditemukan.');
        }

        $bab = trim((string) ($input['bab'] ?? ''));
        $tema = trim((string) ($input['tema'] ?? ''));

        if ($jenis === AiDrafIkhtisar::JENIS_IKHTISAR && $sumber === null) {
            // Ikhtisar selalu merangkum SATU kitab/materi terpilih — sumber
            // adalah identitas dokumennya (adaptasi sadar: sumber = unggahan
            // pesantren sendiri, bukan whitelist GATE-3 yang belum terisi).
            throw new \InvalidArgumentException('Mohon pilih kitab/materi dari Sumber Pengetahuan yang akan diikhtisarkan.');
        }

        if ($jenis === AiDrafIkhtisar::JENIS_KULTUM && $tema === '') {
            throw new \InvalidArgumentException('Mohon isi tema kultum terlebih dahulu.');
        }

        $draf = AiDrafIkhtisar::create([
            'jenis' => $jenis,
            'judul' => $jenis === AiDrafIkhtisar::JENIS_KULTUM
                ? 'Kultum '.$tema
                : 'Ikhtisar '.(string) $sumber?->judul.($bab !== '' ? ' — '.$bab : ''),
            'source_id' => $sourceId,
            'bab' => $jenis === AiDrafIkhtisar::JENIS_IKHTISAR && $bab !== '' ? $bab : null,
            'tema' => $jenis === AiDrafIkhtisar::JENIS_KULTUM ? $tema : null,
            'durasi_menit' => $jenis === AiDrafIkhtisar::JENIS_KULTUM
                ? $this->durasiTerjepit($input['durasi_menit'] ?? null)
                : null,
            'status' => AiDrafIkhtisar::STATUS_ANTRI,
            'isi' => [],
            'sitasi' => [],
            'dibuat_oleh' => $user?->getKey(),
            'idempotency_key' => 'ikhtisar-'.Str::ulid(),
        ]);

        GenerateIkhtisarJob::dispatch(
            (int) $draf->getKey(),
            tenancy()->initialized ? (string) tenant()?->getTenantKey() : null,
        );

        return $draf;
    }

    /** Jepit durasi kultum ke [durasi_min, durasi_max] config; kosong = null. */
    public function durasiTerjepit(int|string|null $durasi): ?int
    {
        if ($durasi === null || $durasi === '') {
            return null;
        }

        $min = max(1, (int) config('ai.generator.ikhtisar.durasi_min', 3));
        $max = max($min, (int) config('ai.generator.ikhtisar.durasi_max', 60));

        return max($min, min((int) $durasi, $max));
    }

    // ------------------------------------------------------------------
    // Pemrosesan (dipanggil GenerateIkhtisarJob di queue 'ai')
    // ------------------------------------------------------------------

    /**
     * Susun isi dokumen — SATU panggilan LLM atas retrieval sumber terpilih
     * (per bab bila diisi). Kegagalan BISNIS (tanpa korpus / degradasi /
     * ditolak kebijakan / format invalid) ditangani anggun → status gagal +
     * pesan santun (TIDAK melempar); exception hanya untuk kegagalan teknis
     * tak terduga (pemanggil/job yang menandai gagal).
     */
    public function proses(int $drafId): AiDrafIkhtisar
    {
        /** @var AiDrafIkhtisar $draf */
        $draf = AiDrafIkhtisar::query()->findOrFail($drafId);

        // Idempoten: sudah final → no-op (re-dispatch/retry tidak mengulang HTTP).
        if (in_array($draf->status, [AiDrafIkhtisar::STATUS_DRAF, AiDrafIkhtisar::STATUS_DISAHKAN], true)) {
            return $draf;
        }

        $draf->update([
            'status' => AiDrafIkhtisar::STATUS_MENYUSUN,
            'error_ringkas' => null,
            'isi' => [],
            'sitasi' => [],
        ]);

        // RAG-only (pagar #2): retrieval kosong → gagal santun; Retriever
        // berhenti SEBELUM embed bila korpus kosong — jalur ini TIDAK memicu
        // HTTP apa pun ke provider.
        $retrieval = $this->retriever->cari($this->queryRetrieval($draf), $this->opsiRetrieval($draf));

        if (! $retrieval->adaSumber) {
            return $this->gagalkan($draf, (string) config(
                'ai.generator.ikhtisar.pesan.tanpa_korpus',
                'Mohon maaf, belum ada materi yang dapat dirujuk untuk menyusun dokumen ini. Silakan unggah materi kitab terlebih dahulu di menu Sumber Pengetahuan, lalu coba kembali.',
            ));
        }

        $konteks = $this->susunKonteks($retrieval);
        $kunci = $draf->idempotency_key.':g1';

        $hasil = $this->gateway->generate($this->requestGenerate(
            $draf,
            prompt: $this->promptUtama($draf),
            konteks: $konteks,
            kunci: $kunci,
        ));

        // Degradasi provider ATAU penolakan kebijakan (termasuk lapis 2
        // kultum: fitur generator_kultum OFF untuk peran pembuat) → gagal
        // santun dengan pesan template-nya.
        if ($hasil->degraded || $hasil->ditolakPolicy) {
            return $this->gagalkan($draf, $hasil->text);
        }

        $isi = $this->parseIsi($draf->jenis, $hasil->text);

        // Parse gagal → retry parse-prompt 1× (kunci ':r1' — outcome berbeda,
        // TIDAK boleh ter-dedup ke panggilan asli) → masih gagal → status
        // gagal dengan pesan rapi.
        if ($isi === null) {
            $hasil = $this->gateway->generate($this->requestGenerate(
                $draf,
                prompt: $this->promptPerbaikanFormat($draf),
                konteks: $konteks,
                kunci: $kunci.':r1',
            ));

            if ($hasil->degraded || $hasil->ditolakPolicy) {
                return $this->gagalkan($draf, $hasil->text);
            }

            $isi = $this->parseIsi($draf->jenis, $hasil->text);

            if ($isi === null) {
                return $this->gagalkan($draf, (string) config(
                    'ai.generator.ikhtisar.pesan.format_invalid',
                    'Mohon maaf, asisten tidak menghasilkan format dokumen yang valid setelah dicoba ulang. Silakan coba beberapa saat lagi.',
                ));
            }
        }

        // SITASI DETERMINISTIK (Blueprint §7.2): kolom `sitasi` dari chunk
        // retrieval — model tidak pernah menulis sitasi.
        $sitasi = $this->sitasiDeterministik($retrieval);

        if ($draf->jenis === AiDrafIkhtisar::JENIS_IKHTISAR) {
            // AC T-A042: tiap poin tertaut sitasi — sitasi_idx menunjuk indeks
            // kolom `sitasi` (seluruh chunk retrieval draf ini; satu retrieval
            // per draf = jangkauan rujukan poin), poin → sumber tertelusur.
            $idx = array_keys($sitasi);
            $isi['poin'] = array_map(
                static fn (array $p): array => $p + ['sitasi_idx' => $idx],
                $isi['poin'],
            );
        } else {
            // Disclaimer WAJIB melekat di output kultum (bukan hanya di UI).
            $isi['disclaimer'] = $this->disclaimerKultum();
        }

        $draf->update([
            'status' => AiDrafIkhtisar::STATUS_DRAF,
            'isi' => $isi,
            'sitasi' => $sitasi,
        ]);

        return $draf->refresh();
    }

    // ------------------------------------------------------------------
    // Aksi manusia (pagar #3: human-in-the-loop)
    // ------------------------------------------------------------------

    /**
     * Pengesahan draf — HANYA pemegang permission ai.generator.ikhtisar;
     * jejak identitas + waktu tercatat. PRD: dokumen selalu lahir DRAF.
     *
     * @throws AuthorizationException|\InvalidArgumentException
     */
    public function sahkan(AiDrafIkhtisar $draf, User $user): AiDrafIkhtisar
    {
        if (! $user->can('ai.generator.ikhtisar')) {
            throw new AuthorizationException('Mohon maaf, akun Anda tidak memiliki izin untuk mengesahkan dokumen ini.');
        }

        if ($draf->status !== AiDrafIkhtisar::STATUS_DRAF) {
            throw new \InvalidArgumentException('Hanya dokumen berstatus "draf" yang dapat disahkan.');
        }

        $draf->update([
            'status' => AiDrafIkhtisar::STATUS_DISAHKAN,
            'disahkan_oleh' => (int) $user->getKey(),
            'disahkan_pada' => now(),
        ]);

        return $draf->refresh();
    }

    /**
     * Edit isi per butir (index) — hanya saat status draf. Yang TERKUNCI
     * (jejak deterministik pipeline): sitasi_idx poin, kolom sitasi, dan
     * disclaimer kultum. Rujukan pengingat_dalil justru DIEDIT ustadz —
     * memang dikosongkan pipeline untuk dilengkapi manusia.
     *
     * @param  array{poin?: list<array{teks?: string}>, istilah?: list<array{istilah?: string, arti?: string}>, pertanyaan_diskusi?: list<string|array{teks?: string}>, kerangka?: list<string|array{teks?: string}>, pengingat_dalil?: list<array{klaim?: string, rujukan?: string}>}  $perubahan
     *
     * @throws \InvalidArgumentException
     */
    public function perbaruiIsi(AiDrafIkhtisar $draf, array $perubahan): AiDrafIkhtisar
    {
        if ($draf->status !== AiDrafIkhtisar::STATUS_DRAF) {
            throw new \InvalidArgumentException('Isi hanya dapat diedit selama dokumen berstatus "draf".');
        }

        $isi = (array) $draf->isi;

        if ($draf->jenis === AiDrafIkhtisar::JENIS_IKHTISAR) {
            foreach ((array) ($isi['poin'] ?? []) as $i => $poin) {
                $edit = $perubahan['poin'][$i] ?? null;

                if (is_array($edit) && array_key_exists('teks', $edit)) {
                    $poin['teks'] = trim((string) $edit['teks']);
                    // 'sitasi_idx' SENGAJA tidak disentuh — jejak pipeline.
                }

                $isi['poin'][$i] = $poin;
            }

            foreach ((array) ($isi['istilah'] ?? []) as $i => $istilah) {
                $edit = $perubahan['istilah'][$i] ?? null;

                if (is_array($edit)) {
                    $istilah['istilah'] = trim((string) ($edit['istilah'] ?? $istilah['istilah'] ?? ''));
                    $istilah['arti'] = trim((string) ($edit['arti'] ?? $istilah['arti'] ?? ''));
                }

                $isi['istilah'][$i] = $istilah;
            }

            foreach ((array) ($isi['pertanyaan_diskusi'] ?? []) as $i => $tanya) {
                if (array_key_exists($i, (array) ($perubahan['pertanyaan_diskusi'] ?? []))) {
                    $edit = $perubahan['pertanyaan_diskusi'][$i];
                    $isi['pertanyaan_diskusi'][$i] = trim(
                        is_array($edit) ? (string) ($edit['teks'] ?? $tanya) : (string) $edit,
                    );
                }
            }
        } else {
            foreach ((array) ($isi['kerangka'] ?? []) as $i => $bagian) {
                if (array_key_exists($i, (array) ($perubahan['kerangka'] ?? []))) {
                    $edit = $perubahan['kerangka'][$i];
                    $isi['kerangka'][$i] = trim(
                        is_array($edit) ? (string) ($edit['teks'] ?? $bagian) : (string) $edit,
                    );
                }
            }

            foreach ((array) ($isi['pengingat_dalil'] ?? []) as $i => $pengingat) {
                $edit = $perubahan['pengingat_dalil'][$i] ?? null;

                if (is_array($edit)) {
                    $pengingat['klaim'] = trim((string) ($edit['klaim'] ?? $pengingat['klaim'] ?? ''));
                    // Rujukan DIISI USTADZ di sini — satu-satunya jalur masuk
                    // teks dalil adalah manusia, bukan model.
                    $pengingat['rujukan'] = trim((string) ($edit['rujukan'] ?? $pengingat['rujukan'] ?? ''));
                }

                $isi['pengingat_dalil'][$i] = $pengingat;
            }
            // 'disclaimer' SENGAJA tidak disentuh — wajib melekat (PRD §7.5).
        }

        $draf->update(['isi' => $isi]);

        return $draf->refresh();
    }

    // ------------------------------------------------------------------
    // Retrieval & prompt
    // ------------------------------------------------------------------

    /** Kueri retrieval: ikhtisar = judul sumber + bab; kultum = tema. */
    private function queryRetrieval(AiDrafIkhtisar $draf): string
    {
        if ($draf->jenis === AiDrafIkhtisar::JENIS_KULTUM) {
            return trim(implode(' ', array_filter([$draf->tema, 'materi pembelajaran'])));
        }

        return trim(implode(' ', array_filter([
            (string) $draf->source?->judul,
            $draf->bab,
            'pokok materi pembelajaran',
        ])));
    }

    /**
     * Opsi retrieval: sumber terpilih menang (sumber itulah otoritas materi);
     * kultum tanpa sumber spesifik = seluruh korpus tenant (tanpa filter
     * kategori — materi kultum bisa hidup di kategori mana pun).
     *
     * @return array{sourceId?: int, topK: int}
     */
    private function opsiRetrieval(AiDrafIkhtisar $draf): array
    {
        $topK = max(1, (int) config('ai.generator.ikhtisar.top_k', 5));

        return $draf->source_id !== null
            ? ['sourceId' => (int) $draf->source_id, 'topK' => $topK]
            : ['topK' => $topK];
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
     * Daftar sitasi deterministik dari chunk retrieval draf ini — bentuk sama
     * dengan AiGenerateResult::sitasi (kompatibel blok-sitasi UI).
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

    private function requestGenerate(AiDrafIkhtisar $draf, string $prompt, string $konteks, string $kunci): AiGenerateRequest
    {
        return (new AiGenerateRequest(
            prompt: $prompt,
            agent: $draf->jenis === AiDrafIkhtisar::JENIS_KULTUM ? self::AGENT_KULTUM : self::AGENT_IKHTISAR,
            tier: 'standar',
            channel: 'web',
            userId: $draf->dibuat_oleh,
            idempotencyKey: $kunci,
            ringkasanKonteks: 'generator_ikhtisar: '.$draf->judul,
        ))->withContext($konteks);
    }

    /**
     * Prompt utama — instruksi TEKNIS netral (tanpa instruksi sitasi: sitasi
     * urusan pipeline; tanpa konten keagamaan: Aturan Emas #8 — kultum hanya
     * meminta STRUKTUR + penanda periksa, bukan dalil).
     */
    private function promptUtama(AiDrafIkhtisar $draf): string
    {
        if ($draf->jenis === AiDrafIkhtisar::JENIS_KULTUM) {
            $bagian = [
                "Susun kerangka ceramah singkat (kultum) bertema \"{$draf->tema}\" dari materi pada konteks.",
            ];

            if ($draf->durasi_menit !== null) {
                $bagian[] = "Sesuaikan banyaknya poin untuk durasi sekitar {$draf->durasi_menit} menit.";
            }

            $bagian[] = 'Tandai setiap pernyataan yang memerlukan rujukan dengan awalan [PERIKSA DALIL] dan biarkan kolom "rujukan" berupa string kosong — jangan menuliskan teks ayat, hadits, maupun terjemahnya.';
            $bagian[] = 'Keluarkan HANYA JSON objek valid dengan kunci "kerangka" dan "pengingat_dalil" sesuai format yang ditetapkan, tanpa teks lain.';

            return implode(' ', $bagian);
        }

        $bagian = [
            'Rangkum materi pada konteks menjadi ikhtisar pembelajaran',
            "untuk kitab/materi \"{$draf->source?->judul}\"",
        ];

        if ($draf->bab !== null) {
            $bagian[] = "khusus bagian \"{$draf->bab}\"";
        }

        $bagian[] = '— poin pembelajaran utama, istilah kunci beserta artinya menurut konteks, dan pertanyaan diskusi.';
        $bagian[] = 'Keluarkan HANYA JSON objek valid dengan kunci "poin", "istilah", dan "pertanyaan_diskusi" sesuai format yang ditetapkan, tanpa teks lain.';

        return implode(' ', $bagian);
    }

    /** Prompt retry-parse: ulangi permintaan dengan penegasan format. */
    private function promptPerbaikanFormat(AiDrafIkhtisar $draf): string
    {
        return 'Jawaban sebelumnya bukan JSON yang valid. '
            .$this->promptUtama($draf)
            .' Pastikan keluaran berupa JSON objek murni: mulai dengan karakter "{" dan diakhiri "}", tanpa penjelasan apa pun.';
    }

    // ------------------------------------------------------------------
    // Parsing JSON toleran
    // ------------------------------------------------------------------

    /**
     * Parse keluaran model per jenis. null = tidak ada isi valid sama sekali
     * (pemanggil melakukan retry format 1×).
     *
     * @return array<string, mixed>|null
     */
    private function parseIsi(string $jenis, string $teks): ?array
    {
        foreach ($this->kandidatJson($teks) as $kandidat) {
            $data = json_decode($kandidat, true);

            if (! is_array($data)) {
                continue;
            }

            $isi = $jenis === AiDrafIkhtisar::JENIS_KULTUM
                ? $this->validasiKultum($data)
                : $this->validasiIkhtisar($data);

            if ($isi !== null) {
                return $isi;
            }
        }

        return null;
    }

    /**
     * Ikhtisar valid minimal punya satu poin non-kosong; istilah & pertanyaan
     * toleran (boleh kosong / bentuk longgar).
     *
     * @param  array<string, mixed>  $data
     * @return array{poin: list<array{teks: string}>, istilah: list<array{istilah: string, arti: string}>, pertanyaan_diskusi: list<string>}|null
     */
    private function validasiIkhtisar(array $data): ?array
    {
        $poin = [];

        foreach ((array) ($data['poin'] ?? []) as $item) {
            $teks = trim(is_array($item) ? (string) ($item['teks'] ?? '') : (string) $item);

            if ($teks !== '') {
                $poin[] = ['teks' => $teks];
            }
        }

        if ($poin === []) {
            return null;
        }

        $istilah = [];

        foreach ((array) ($data['istilah'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $kata = trim((string) ($item['istilah'] ?? ''));

            if ($kata !== '') {
                $istilah[] = ['istilah' => $kata, 'arti' => trim((string) ($item['arti'] ?? ''))];
            }
        }

        $tanya = [];

        foreach ((array) ($data['pertanyaan_diskusi'] ?? []) as $item) {
            $teks = trim(is_array($item) ? (string) ($item['teks'] ?? ($item['pertanyaan'] ?? '')) : (string) $item);

            if ($teks !== '') {
                $tanya[] = $teks;
            }
        }

        return [
            'poin' => array_slice($poin, 0, self::MAX_BUTIR),
            'istilah' => array_slice($istilah, 0, self::MAX_BUTIR),
            'pertanyaan_diskusi' => array_slice($tanya, 0, self::MAX_BUTIR),
        ];
    }

    /**
     * Kultum valid minimal punya satu bagian kerangka non-kosong. Normalisasi
     * DETERMINISTIK pipeline (model tidak dipercaya): klaim pengingat_dalil
     * dipaksa berawalan [PERIKSA DALIL]; rujukan SELALU dikosongkan — diisi
     * ustadz lewat editor (Aturan Emas: JANGAN mengarang ayat/hadits/dalil).
     *
     * @param  array<string, mixed>  $data
     * @return array{kerangka: list<string>, pengingat_dalil: list<array{klaim: string, rujukan: string}>}|null
     */
    private function validasiKultum(array $data): ?array
    {
        $kerangka = [];

        foreach ((array) ($data['kerangka'] ?? []) as $item) {
            $teks = trim(is_array($item) ? (string) ($item['teks'] ?? ($item['judul'] ?? '')) : (string) $item);

            if ($teks !== '') {
                $kerangka[] = $teks;
            }
        }

        if ($kerangka === []) {
            return null;
        }

        $pengingat = [];

        foreach ((array) ($data['pengingat_dalil'] ?? []) as $item) {
            $klaim = trim(is_array($item) ? (string) ($item['klaim'] ?? ($item['teks'] ?? '')) : (string) $item);

            if ($klaim === '') {
                continue;
            }

            if (! str_starts_with($klaim, '[PERIKSA DALIL]')) {
                $klaim = '[PERIKSA DALIL] '.$klaim;
            }

            $pengingat[] = ['klaim' => $klaim, 'rujukan' => ''];
        }

        return [
            'kerangka' => array_slice($kerangka, 0, self::MAX_BUTIR),
            'pengingat_dalil' => array_slice($pengingat, 0, self::MAX_BUTIR),
        ];
    }

    /** @return list<string> kandidat string JSON, paling spesifik dulu (pola SoalGenerator) */
    private function kandidatJson(string $teks): array
    {
        $teks = trim($teks);
        $kandidat = [];

        if (preg_match('/```(?:json)?\s*(.+?)```/su', $teks, $m)) {
            $kandidat[] = trim($m[1]);
        }

        $kandidat[] = $teks;

        $a = strpos($teks, '{');
        $b = strrpos($teks, '}');

        if ($a !== false && $b !== false && $b > $a) {
            $kandidat[] = substr($teks, $a, $b - $a + 1);
        }

        return array_values(array_unique($kandidat));
    }

    // ------------------------------------------------------------------
    // Disclaimer kultum (PRD §7.5 + teks prosedural tambahan)
    // ------------------------------------------------------------------

    /**
     * Disclaimer WAJIB output kultum: teks kategori 'agama' PRD §7.5 via
     * DisclaimerInjector (override tenant rule T-A020 tetap dihormati) +
     * teks prosedural tetap dari config (netral, bukan konten fikih).
     */
    public function disclaimerKultum(): string
    {
        $agama = trim(app(DisclaimerInjector::class)->inject('', CitationEnforcer::KATEGORI_AGAMA));

        $tambahan = trim((string) config(
            'ai.generator.ikhtisar.disclaimer_kultum',
            'Kerangka ini wajib diperiksa dan dilengkapi dalil oleh Ustadz sebelum disampaikan.',
        ));

        return trim($agama."\n".$tambahan);
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function gagalkan(AiDrafIkhtisar $draf, string $pesan): AiDrafIkhtisar
    {
        $draf->update([
            'status' => AiDrafIkhtisar::STATUS_GAGAL,
            'error_ringkas' => mb_substr($pesan, 0, 500),
        ]);

        return $draf->refresh();
    }
}
