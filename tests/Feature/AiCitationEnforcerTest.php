<?php

namespace Tests\Feature;

use App\Services\StorageQuotaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Gateway\AiGenerateResult;
use Modules\AI\Services\Guardrails\CitationEnforcer;
use Modules\AI\Services\Guardrails\DisclaimerInjector;
use Modules\AI\Services\Rag\IngestService;
use Modules\AI\Services\Rag\RagAnswerer;
use Modules\AI\Services\Rag\RetrievalResult;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Tools\ToolResult;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A022 — Citation enforcer & disclaimer injector:
 *  (a) jawaban berakar konteks + kategori agama → lolos + disclaimer PRD
 *      §7.5 VERBATIM + daftar sumber, format WA-safe (tanpa markdown);
 *  (b) jawaban ngawur → regenerasi 1× (kunci '-regen', TIDAK ter-dedup:
 *      2 baris audit + 2 baris ledger) → masih ngawur → penolakan santun +
 *      eskalasi 'sitasi_gagal'; jawaban ngawur TIDAK PERNAH ada di hasil;
 *  (c) ngawur lalu regenerasi berhasil → jawaban regen dipakai, sitasi
 *      tertaut baris audit '-regen';
 *  (d) unit enforcer: kategori agama tanpa konteks → tolak; umum → lolos;
 *  (e) unit injector: footer sumber+cap waktu jawaban data (ToolResult).
 *
 * Heuristik enforcer DETERMINISTIK TANPA LLM — tidak ada panggilan model
 * untuk verifikasi, dan model tidak pernah diminta mengarang sitasi.
 */
class AiCitationEnforcerTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const DIM = 1024;

    private const JAWABAN_NGAWUR = 'Planet Jupiter memiliki puluhan satelit alami. Galaksi Bima Sakti berputar mengelilingi pusat gravitasi raksasa.';

    private const JAWABAN_BERAKAR = 'Tamu wajib lapor ke pos keamanan dan mengisi buku kunjungan. Jam kunjungan wali santri adalah hari Ahad pukul sembilan pagi.';

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady(['ai_knowledge_source', 'ai_chunk', 'ai_citation']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        \Illuminate\Support\Facades\DB::table('module_registry')->delete();
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function siapkanTenant(string $prefix): \App\Models\Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(AiSettingProvisioner::class)->provision((string) $tenant->id);

        Storage::fake(StorageQuotaService::diskName());
        Queue::fake();

        return $tenant;
    }

    /** @return list<float> */
    private static function vektorTeks(string $teks): array
    {
        $v = array_fill(0, self::DIM, 0.0);

        $kata = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($kata as $k) {
            $v[hexdec(substr(md5($k), 0, 6)) % self::DIM] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $x): float => $x * $x, $v)));

        if ($norm <= 0.0) {
            $v[0] = 1.0;

            return $v;
        }

        return array_map(static fn (float $x): float => $x / $norm, $v);
    }

    /**
     * Fake HTTP Ollama: embed deterministik + generate berurutan sesuai
     * daftar $jawaban (panggilan ke-N memakai jawaban ke-N; lebih dari itu
     * memakai jawaban terakhir).
     *
     * @param  list<string>  $jawaban
     */
    private function fakeOllama(array $jawaban): void
    {
        $hitung = new \stdClass;
        $hitung->n = 0;

        Http::fake([
            '*/api/embed' => function ($request) {
                $inputs = (array) ($request['input'] ?? []);

                return Http::response([
                    'embeddings' => array_map(
                        static fn ($t): array => self::vektorTeks((string) $t),
                        $inputs,
                    ),
                ]);
            },
            '*/api/generate' => function () use ($hitung, $jawaban) {
                $teks = $jawaban[min($hitung->n, count($jawaban) - 1)];
                $hitung->n++;

                return Http::response([
                    'response' => $teks,
                    'prompt_eval_count' => 25,
                    'eval_count' => 12,
                ]);
            },
        ]);
    }

    private function ingestSopTamu(): void
    {
        $service = app(IngestService::class);
        $source = $service->daftarkanSumber(
            'SOP Kunjungan Tamu',
            'sop',
            'Tamu wajib lapor ke pos keamanan dan mengisi buku kunjungan. Jam kunjungan wali santri adalah hari Ahad pukul sembilan pagi sampai tiga sore.',
        );
        $service->prosesSumber((int) $source->getKey());
    }

    private function request(string $prompt, string $key): AiGenerateRequest
    {
        return new AiGenerateRequest(
            prompt: $prompt,
            agent: 'uji',
            tier: 'ekonomis',
            channel: 'web',
            idempotencyKey: $key,
        );
    }

    private function jumlahGenerate(): int
    {
        return Http::recorded(
            static fn ($req): bool => str_contains($req->url(), '/api/generate'),
        )->count();
    }

    // ------------------------------------------------------------------
    // (a) Lolos + disclaimer agama VERBATIM PRD §7.5, WA-safe
    // ------------------------------------------------------------------

    public function test_jawaban_berakar_konteks_kategori_agama_lolos_dengan_disclaimer_prd_verbatim(): void
    {
        $this->siapkanTenant('enfa');
        $this->fakeOllama([self::JAWABAN_BERAKAR]);
        $this->ingestSopTamu();

        $hasil = app(RagAnswerer::class)->jawab(
            $this->request('Bagaimana aturan jam kunjungan tamu wali santri?', 'uji-enf-agama-1'),
            ['kategori_jawaban' => 'agama'],
        );

        $this->assertNotNull($hasil->verdictSitasi);
        $this->assertTrue($hasil->verdictSitasi->lolos);
        $this->assertSame(1, $this->jumlahGenerate(), 'lolos verifikasi pertama = tanpa regenerasi');

        // Jawaban model tetap di awal + daftar sumber + disclaimer PRD §7.5
        // VERBATIM (teks dari PRD, bukan karangan).
        $this->assertStringStartsWith(self::JAWABAN_BERAKAR, $hasil->text);
        $this->assertStringContainsString("Sumber rujukan:\n- SOP Kunjungan Tamu", $hasil->text);
        $this->assertStringContainsString(
            'Ini alat bantu belajar, bukan fatwa. Untuk amalan, mohon merujuk kepada Ustadz/Kyai yang sanadnya jelas.',
            $hasil->text,
        );

        // Format WA-safe: tanpa heading/bold markdown.
        $this->assertDoesNotMatchRegularExpression('/^#|\n#/u', $hasil->text);
        $this->assertStringNotContainsString('**', $hasil->text);
    }

    public function test_kategori_umum_tidak_mendapat_disclaimer_agama(): void
    {
        $this->siapkanTenant('enfu');
        $this->fakeOllama([self::JAWABAN_BERAKAR]);
        $this->ingestSopTamu();

        // Agen 'uji' tidak terpetakan di kategori_map → default 'umum'.
        $hasil = app(RagAnswerer::class)->jawab(
            $this->request('Bagaimana aturan jam kunjungan tamu wali santri?', 'uji-enf-umum-1'),
        );

        $this->assertTrue($hasil->verdictSitasi->lolos);
        $this->assertSame(self::JAWABAN_BERAKAR, $hasil->text, 'umum = tanpa disclaimer/sumber tambahan');
        $this->assertStringNotContainsString('bukan fatwa', $hasil->text);
    }

    // ------------------------------------------------------------------
    // (b) Ngawur 2× → regenerasi → penolakan santun + eskalasi
    // ------------------------------------------------------------------

    public function test_jawaban_ngawur_dua_kali_ditolak_santun_dengan_eskalasi_dan_tanpa_dedup_metering(): void
    {
        $tenant = $this->siapkanTenant('enfn');
        $this->fakeOllama([self::JAWABAN_NGAWUR, self::JAWABAN_NGAWUR]);
        $this->ingestSopTamu();

        $key = 'uji-enf-ngawur-1';
        $hasil = app(RagAnswerer::class)->jawab(
            $this->request('Bagaimana aturan jam kunjungan tamu wali santri?', $key),
            ['kategori_jawaban' => 'agama'],
        );

        // Regenerasi tepat 1× → total 2 panggilan generate.
        $this->assertSame(2, $this->jumlahGenerate());

        // Penolakan santun — jawaban ngawur TIDAK PERNAH sampai ke pemanggil.
        $this->assertSame((string) config('ai.guardrails.pesan.sitasi_gagal'), $hasil->text);
        $this->assertStringNotContainsString('Jupiter', $hasil->text);
        $this->assertSame('null', $hasil->provider);
        $this->assertSame([], $hasil->sitasi);
        $this->assertNotNull($hasil->verdictSitasi);
        $this->assertFalse($hasil->verdictSitasi->lolos);

        // Eskalasi Tanya Ustadz tercatat idempoten, kategori 'sitasi_gagal'.
        app(RagAnswerer::class)->jawab(
            $this->request('Bagaimana aturan jam kunjungan tamu wali santri?', $key),
            ['kategori_jawaban' => 'agama'],
        );
        $this->assertSame(1, AiEscalation::query()->where('idempotency_key', $key)->count());
        $this->assertSame(
            'sitasi_gagal',
            AiEscalation::query()->where('idempotency_key', $key)->value('kategori'),
        );

        // Kunci regenerasi '-regen' = baris audit & ledger TERPISAH (tidak
        // ter-dedup keliru oleh idempotensi metering).
        $this->assertSame(
            2,
            AiAuditLog::query()->whereIn('idempotency_key', [$key, $key.'-regen'])->count(),
        );
        $this->assertSame(
            2,
            AiCreditLedger::query()
                ->where('tenant_id', (string) $tenant->id)
                ->whereIn('idempotency_key', [$key, $key.'-regen'])
                ->count(),
            'ledger wajib 2 baris — kunci -regen tidak boleh ter-dedup',
        );
    }

    // ------------------------------------------------------------------
    // (c) Ngawur lalu regenerasi BERHASIL → jawaban regen dipakai
    // ------------------------------------------------------------------

    public function test_regenerasi_berhasil_memakai_jawaban_kedua_dan_sitasi_tertaut_audit_regen(): void
    {
        $this->siapkanTenant('enfr');
        $this->fakeOllama([self::JAWABAN_NGAWUR, self::JAWABAN_BERAKAR]);
        $this->ingestSopTamu();

        $key = 'uji-enf-regen-1';
        $hasil = app(RagAnswerer::class)->jawab(
            $this->request('Bagaimana aturan jam kunjungan tamu wali santri?', $key),
            ['kategori_jawaban' => 'agama'],
        );

        $this->assertSame(2, $this->jumlahGenerate());
        $this->assertTrue($hasil->verdictSitasi->lolos);
        $this->assertStringStartsWith(self::JAWABAN_BERAKAR, $hasil->text);
        $this->assertStringNotContainsString('Jupiter', $hasil->text);
        $this->assertNotSame([], $hasil->sitasi);

        // Sitasi tertaut baris audit panggilan yang MENJAWAB ('-regen').
        $citation = \Modules\AI\Models\AiCitation::query()->orderBy('urutan')->firstOrFail();
        $this->assertSame(
            $key.'-regen',
            AiAuditLog::query()->whereKey($citation->audit_log_id)->value('idempotency_key'),
        );
    }

    // ------------------------------------------------------------------
    // (d) Unit enforcer: tanpa konteks
    // ------------------------------------------------------------------

    public function test_enforcer_kategori_agama_tanpa_konteks_ditolak_dan_umum_lolos(): void
    {
        $enforcer = new CitationEnforcer;
        $jawaban = new AiGenerateResult(text: 'Jawaban apa pun.', provider: 'ollama', model: 'uji');

        $agama = $enforcer->verifikasi($jawaban, RetrievalResult::kosong(), 'agama');
        $this->assertFalse($agama->lolos);
        $this->assertSame(0.0, $agama->skorOverlap);

        $umum = $enforcer->verifikasi($jawaban, RetrievalResult::kosong(), 'umum');
        $this->assertTrue($umum->lolos);

        // Degradasi/tolakan kebijakan = lolos otomatis (bukan klaim).
        $degraded = new AiGenerateResult(text: 'Sibuk.', provider: 'null', model: 'null', degraded: true);
        $this->assertTrue($enforcer->verifikasi($degraded, RetrievalResult::kosong(), 'agama')->lolos);
    }

    // ------------------------------------------------------------------
    // (e) Unit injector: footer data + WA-safe + disclaimer kategori lain
    // ------------------------------------------------------------------

    public function test_injector_footer_sumber_dan_cap_waktu_untuk_jawaban_data(): void
    {
        $injector = new DisclaimerInjector;

        $tool = ToolResult::ok('getStatusPPDB', [
            'nama' => 'Fulan',
            'nomor_pendaftaran' => 'PPDB-001',
            'status_pendaftaran' => 'diterima',
            'gelombang' => '1',
            'tahun_ajaran' => '2026/2027',
        ], 'Modul PPDB');

        $teks = $injector->inject($tool->keTeks(), 'umum', [], $tool->capWaktu, (string) $tool->sumber);

        $this->assertStringContainsString('Status pendaftaran atas nama Fulan', $teks);
        $this->assertStringContainsString('Sumber: Modul PPDB • per '.$tool->capWaktu, $teks);
        $this->assertStringNotContainsString('**', $teks);
        $this->assertStringNotContainsString('# ', $teks);
    }

    public function test_injector_disclaimer_keuangan_dan_akademik_dari_config(): void
    {
        $injector = new DisclaimerInjector;

        $keuangan = $injector->inject('Saldo kas bulan ini surplus.', 'keuangan');
        $this->assertStringContainsString((string) config('ai.guardrails.disclaimer.keuangan'), $keuangan);

        $akademik = $injector->inject('Materi bab dua membahas tata bahasa.', 'akademik');
        $this->assertStringContainsString((string) config('ai.guardrails.disclaimer.akademik'), $akademik);

        // Kategori umum: teks tidak ditambah apa-apa.
        $this->assertSame('Jawaban biasa.', $injector->inject('Jawaban biasa.', 'umum'));
    }
}
