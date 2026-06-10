<?php

namespace Modules\AI\Services\Guardrails;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiPolicy;
use Modules\AI\Models\AiPolicyRule;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Gateway\AiGenerateResult;
use Modules\AI\Services\Privacy\PiiRedactor;
use Modules\AI\Services\Settings\TenantSettingService;
use Throwable;

/**
 * T-A020 — Sharia Guardrails: policy engine pre-prompt & post-response.
 *
 * MURNI RULE-BASED, TANPA LLM (Blueprint §4.2 langkah 1): match → respons
 * template + log, SELESAI. Urutan evaluasi pre() MURAH → MAHAL:
 *
 *   a. gerbang aktivasi  — ada pengaturan aktif (Wizard tuntas; versi aktif
 *      hanya lahir dari provisioning v1 atau lifecycle approval T-A010,
 *      jadi keberadaannya = sudah melewati gerbang approval kyai; saldo
 *      kredit/kill-switch SUDAH dicek BudgetGuard di gateway — tidak dobel);
 *   b. jam layanan       — hanya kanal eksternal (config jam_layanan_channels);
 *   c. scope kanal/peran — fitur_per_peran setting × peran spatie user;
 *   d. blacklist topik   — rule ai_policy_rule aktif (mirror setting + preset
 *      GATE-3 kelak), keyword kata-utuh / regex, case-insensitive;
 *   e. level fatwa       — edukatif_only + pola tanya-hukum (pola TEKNIS-
 *      LINGUISTIK di config ai.guardrails.pola_hukum, BUKAN konten agama).
 *
 * Aturan Emas #8: kelas ini TIDAK memuat konten keagamaan apa pun — isi
 * blacklist/preset datang dari data (setting tenant / seed GATE-3).
 */
class PolicyEngine
{
    public function __construct(
        private readonly PiiRedactor $redactor,
    ) {}

    /**
     * Evaluasi pra-prompt. Hanya bermakna dalam konteks tenant — pemanggil
     * central (CLI ai:health) lolos tanpa evaluasi.
     */
    public function pre(AiGenerateRequest $request, ?string $tenantId): PolicyDecision
    {
        if ($tenantId === null) {
            return PolicyDecision::lolos('konteks central — kebijakan tenant tidak berlaku');
        }

        // (a) Gerbang aktivasi: tanpa pengaturan aktif = Wizard belum tuntas.
        // Resolusi FRESH (bukan constructor injection): AiGateway singleton —
        // instance TenantSettingService yang di-inject sekali akan membawa
        // memo per-request yang basi saat versi aktif berganti / worker
        // berumur panjang.
        $setting = app(TenantSettingService::class)->aktif();

        if ($setting === null) {
            return $this->blokir(
                $request,
                kategori: 'belum_dikonfigurasi',
                alasan: 'tidak ada ai_tenant_setting berstatus aktif (Wizard belum tuntas)',
                template: $this->template('belum_dikonfigurasi'),
                eskalasi: false,
            );
        }

        $payload = (array) $setting->payload;

        // (b) Jam layanan — hanya kanal eksternal (wa/chat wali); staf
        // internal/web bebas jam.
        if ($keputusan = $this->cekJamLayanan($request, $payload)) {
            return $keputusan;
        }

        // (c) Scope kanal/peran: agent_key → fitur (peta config); agen tanpa
        // pemetaan: kanal internal bebas (uji/dev), kanal EKSTERNAL fail-closed.
        if ($keputusan = $this->cekFiturPerPeran($request, $payload)) {
            return $keputusan;
        }

        // [K1] Teks yang diperiksa rule konten = prompt + context: context
        // (dokumen/RAG) ikut masuk ke provider, jadi WAJIB ikut dipindai —
        // tanpa ini keyword blacklist bisa diselundupkan lewat context.
        $teksPeriksa = $this->teksPeriksa($request);

        // (d) Blacklist topik: rule data (mirror setting + preset GATE-3).
        if ($keputusan = $this->cekBlacklist($request, $teksPeriksa)) {
            return $keputusan;
        }

        // (e) Level fatwa: edukatif_only → pertanyaan berpola hukum dialihkan
        // ke Tanya Ustadz. informatif/khilafiyah lolos pre (sitasi = T-A022).
        if ($keputusan = $this->cekLevelFatwa($request, $payload, $teksPeriksa)) {
            return $keputusan;
        }

        return PolicyDecision::lolos();
    }

    /**
     * Evaluasi post-response di GATEWAY. Tetap pass-through SENGAJA:
     * injeksi disclaimer TIDAK boleh terjadi di gateway karena jalur RAG
     * (RagAnswerer) menyuntik sendiri SETELAH enforcer T-A022 — injeksi
     * dobel akan menumpuk disclaimer. JANGAN tambah logika blokir di sini
     * tanpa memperbarui kontrak audit gateway.
     */
    public function post(AiGenerateResult $result, PolicyDecision $pre): PolicyDecision
    {
        // T-A022: jalur RAG memakai CitationEnforcer + DisclaimerInjector
        // langsung di RagAnswerer; jalur NON-RAG memakai postNonRag() di
        // bawah. Keputusan pre diteruskan apa adanya.
        return $pre;
    }

    /**
     * T-A022 — hook post untuk jalur NON-RAG (jawaban tool/template, chatbot
     * rule-based T-A051 kelak): suntik disclaimer sesuai kategori jawaban.
     * Jawaban degradasi/tolakan kebijakan dilewatkan apa adanya (pesan
     * prosedural, bukan klaim yang butuh disclaimer). Footer sumber+cap waktu
     * untuk jawaban data dipasok pemanggil dari ToolResult.
     */
    public function postNonRag(
        AiGenerateResult $result,
        string $kategori,
        ?string $capWaktu = null,
        ?string $sumber = null,
    ): AiGenerateResult {
        if ($result->degraded || $result->ditolakPolicy) {
            return $result;
        }

        $text = app(DisclaimerInjector::class)->inject(
            $result->text,
            $kategori,
            $result->sitasi,
            $capWaktu,
            $sumber,
        );

        return new AiGenerateResult(
            text: $text,
            provider: $result->provider,
            model: $result->model,
            degraded: $result->degraded,
            promptTokens: $result->promptTokens,
            completionTokens: $result->completionTokens,
            latencyMs: $result->latencyMs,
            raw: $result->raw,
            ditolakPolicy: $result->ditolakPolicy,
            sitasi: $result->sitasi,
            verdictSitasi: $result->verdictSitasi,
        );
    }

    /**
     * Mirror blacklist_topik dari versi pengaturan AKTIF ke ai_policy_rule
     * (policy tenant "Kebijakan Pesantren"). IDEMPOTEN — dipanggil setiap
     * versi aktif berubah (TenantSettingService::ajukan/putuskan).
     *
     * @param  array<string, mixed>  $payload  payload versi aktif
     */
    public function syncDariSetting(array $payload): void
    {
        $policy = AiPolicy::query()->firstOrCreate(
            ['nama' => AiPolicy::NAMA_KEBIJAKAN_PESANTREN, 'sumber' => AiPolicy::SUMBER_TENANT],
            ['aktif' => true],
        );

        $keywords = array_values(array_filter(
            array_map(
                static fn ($k): string => trim((string) $k),
                (array) ($payload['blacklist_topik'] ?? []),
            ),
            static fn (string $k): bool => $k !== '',
        ));

        // Satu rule mirror per policy (updateOrCreate atas policy_id+tipe
        // dengan prioritas penanda 0) — rule blacklist lain (preset GATE-3 /
        // tambahan manual) hidup di baris/policy lain, tidak tersentuh.
        AiPolicyRule::query()->updateOrCreate(
            [
                'policy_id' => $policy->getKey(),
                'tipe' => AiPolicyRule::TIPE_BLACKLIST_TOPIK,
                'prioritas' => 0,
            ],
            [
                'pola' => ['keyword' => $keywords, 'regex' => []],
                'respons_template' => null, // template default config
                'aktif' => $keywords !== [],
            ],
        );
    }

    // ------------------------------------------------------------------
    // Gerbang individual
    // ------------------------------------------------------------------

    /** @param  array<string, mixed>  $payload */
    private function cekJamLayanan(AiGenerateRequest $request, array $payload): ?PolicyDecision
    {
        $kanalTerikat = (array) config('ai.guardrails.jam_layanan_channels', ['wa', 'chat']);

        if (! in_array($request->channel, $kanalTerikat, true)) {
            return null;
        }

        $jam = (array) ($payload['jam_layanan'] ?? []);
        $mulai = (string) ($jam['mulai'] ?? '00:00');
        $selesai = (string) ($jam['selesai'] ?? '23:59');
        $sekarang = now()->format('H:i');

        // Rentang biasa (mulai <= selesai) atau melewati tengah malam.
        $diDalam = $mulai <= $selesai
            ? ($sekarang >= $mulai && $sekarang <= $selesai)
            : ($sekarang >= $mulai || $sekarang <= $selesai);

        if ($diDalam) {
            return null;
        }

        return $this->blokir(
            $request,
            kategori: 'jam_layanan',
            alasan: "di luar jam layanan {$mulai}-{$selesai} (kanal {$request->channel})",
            template: (string) ($payload['pesan_di_luar_jam'] ?? $this->template('di_luar_jam')),
            eskalasi: false,
        );
    }

    /** @param  array<string, mixed>  $payload */
    private function cekFiturPerPeran(AiGenerateRequest $request, array $payload): ?PolicyDecision
    {
        $fitur = config("ai.guardrails.agent_feature_map.{$request->agent}");

        if (! is_string($fitur)) {
            // [K3] Agen tanpa pemetaan fitur: kanal INTERNAL (web/cli/null)
            // bebas — agen uji/dev. Kanal EKSTERNAL fail-closed: agent key
            // asal-asalan dari WA/chat tidak boleh lolos gerbang fitur/peran.
            $kanalEksternal = (array) config('ai.guardrails.kanal_eksternal', ['wa', 'chat']);

            if (! in_array($request->channel, $kanalEksternal, true)) {
                return null;
            }

            return $this->blokir(
                $request,
                kategori: 'fitur_tidak_dikenali',
                alasan: "agen '{$request->agent}' tidak terpetakan di agent_feature_map"
                    ." (kanal eksternal {$request->channel} = fail-closed)",
                template: $this->template('fitur_tidak_dikenali'),
                eskalasi: false,
            );
        }

        $matriks = (array) ($payload['fitur_per_peran'] ?? []);
        $roleMap = (array) config('ai.guardrails.role_map', []);

        $peranUser = $this->peranUser($request->userId, $roleMap);

        foreach ($peranUser as $peran) {
            if (($matriks[$peran][$fitur] ?? false) === true) {
                return null; // satu peran saja yang mengizinkan = cukup
            }
        }

        return $this->blokir(
            $request,
            kategori: 'fitur_per_peran',
            alasan: "fitur '{$fitur}' (agen {$request->agent}) tidak diizinkan untuk peran ["
                .implode(',', $peranUser).'] user '.($request->userId ?? '-'),
            template: $this->template('fitur_tidak_diizinkan'),
            eskalasi: false,
        );
    }

    private function cekBlacklist(AiGenerateRequest $request, string $teksPeriksa): ?PolicyDecision
    {
        foreach ($this->ruleBlacklistAktif() as $rule) {
            if ($this->rulePolaCocok($rule, $teksPeriksa)) {
                return $this->blokir(
                    $request,
                    kategori: 'blacklist_topik',
                    alasan: "prompt/context cocok rule blacklist #{$rule->getKey()}",
                    template: $rule->respons_template ?? $this->template('blacklist_topik'),
                    eskalasi: true,
                    ruleId: (int) $rule->getKey(),
                );
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    private function cekLevelFatwa(AiGenerateRequest $request, array $payload, string $teksPeriksa): ?PolicyDecision
    {
        if (($payload['level_fatwa'] ?? null) !== 'edukatif_only') {
            return null;
        }

        // Pola TEKNIS-LINGUISTIK pertanyaan hukum (config) — bukan daftar
        // topik agama (Aturan Emas #8). [K1] context ikut dipindai.
        foreach ((array) config('ai.guardrails.pola_hukum', []) as $regex) {
            if (@preg_match((string) $regex, $teksPeriksa) === 1) {
                return $this->blokir(
                    $request,
                    kategori: 'level_fatwa',
                    alasan: "level_fatwa=edukatif_only + prompt berpola tanya-hukum ({$regex})",
                    template: $this->template('tanya_ustadz'),
                    eskalasi: true,
                );
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /**
     * [K2] Pagar blacklist untuk jalur embed(). embed bukan jawaban kepada
     * pengguna — TIDAK ada disclaimer/level fatwa/eskalasi di sini; satu-
     * satunya rule yang relevan = blacklist topik (jangan pernah meng-index
     * konten terlarang ke vektor). true = pemanggil WAJIB berhenti TANPA HTTP.
     *
     * @param  list<string>  $inputs
     */
    public function embedDiblokir(array $inputs, ?string $tenantId): bool
    {
        if ($tenantId === null) {
            return false; // konteks central (ai:health CLI) — tanpa rule tenant
        }

        $teks = implode("\n", array_map(static fn ($i): string => (string) $i, $inputs));

        foreach ($this->ruleBlacklistAktif() as $rule) {
            if ($this->rulePolaCocok($rule, $teks)) {
                Log::warning('PolicyEngine: input embed cocok rule blacklist — embedding DIBATALKAN (tanpa HTTP).', [
                    'rule_id' => $rule->getKey(),
                    'jumlah_input' => count($inputs),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * [K1] Teks yang dipindai rule konten: prompt + context digabung —
     * keduanya sama-sama mengalir ke provider.
     */
    private function teksPeriksa(AiGenerateRequest $request): string
    {
        $context = $request->context !== null ? trim($request->context) : '';

        return $context === '' ? $request->prompt : $request->prompt."\n".$context;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, AiPolicyRule> */
    private function ruleBlacklistAktif()
    {
        return AiPolicyRule::query()
            ->where('tipe', AiPolicyRule::TIPE_BLACKLIST_TOPIK)
            ->where('aktif', true)
            ->whereHas('policy', fn ($q) => $q->where('aktif', true))
            ->orderBy('prioritas')
            ->orderBy('id')
            ->get();
    }

    private function rulePolaCocok(AiPolicyRule $rule, string $prompt): bool
    {
        $pola = (array) $rule->pola;

        foreach ((array) ($pola['keyword'] ?? []) as $keyword) {
            $keyword = trim((string) $keyword);

            if ($keyword === '') {
                continue;
            }

            // Kata utuh, case-insensitive, aman-unicode.
            $regex = '/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/iu';

            if (preg_match($regex, $prompt) === 1) {
                return true;
            }
        }

        foreach ((array) ($pola['regex'] ?? []) as $regex) {
            $hasil = @preg_match((string) $regex, $prompt);

            if ($hasil === 1) {
                return true;
            }

            // [T1] FAIL-CLOSED: regex rule blacklist yang GAGAL dievaluasi
            // (invalid / backtrack limit ReDoS) diperlakukan sebagai MATCH
            // konservatif — rule pengaman yang rusak tidak boleh diam-diam
            // jadi celah. Validasi saat simpan ada di AiPolicyRule::booted.
            if ($hasil === false) {
                Log::error('PolicyEngine: regex rule blacklist GAGAL dievaluasi — fail-closed (dianggap match). Perbaiki/nonaktifkan rule ini.', [
                    'rule_id' => $rule->getKey(),
                    'regex' => (string) $regex,
                    'preg_error' => preg_last_error_msg(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Peran-peran user dalam istilah matriks fitur_per_peran setting.
     * Tanpa user / tanpa role = daftar kosong → tidak ada fitur yang lolos
     * (fail-closed untuk agen yang terpetakan fitur).
     *
     * @param  array<string, string>  $roleMap
     * @return list<string>
     */
    private function peranUser(?int $userId, array $roleMap): array
    {
        if ($userId === null) {
            return [];
        }

        $user = User::query()->find($userId);

        if ($user === null || ! method_exists($user, 'getRoleNames')) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (string $role): string => (string) ($roleMap[$role] ?? $role),
            $user->getRoleNames()->all(),
        )));
    }

    private function blokir(
        AiGenerateRequest $request,
        string $kategori,
        string $alasan,
        string $template,
        bool $eskalasi,
        ?int $ruleId = null,
    ): PolicyDecision {
        if ($eskalasi) {
            $this->tulisEskalasi($request, $kategori);
        }

        return new PolicyDecision(
            boleh: false,
            alasan: $alasan,
            responsTemplate: $template,
            ruleId: $ruleId,
            kategori: $kategori,
            eskalasi: $eskalasi,
        );
    }

    /**
     * Antrean Tanya Ustadz dasar: pertanyaan disimpan RINGKAS + TERREDAKSI
     * PII. Best-effort — kegagalan tulis tidak menggagalkan respons blokir
     * (keputusan blokirnya sendiri tetap tercatat di ai_audit_log).
     *
     * [M1] Redaksi di sini PAKSA (redactPaksa, abaikan flag global
     * ai.privacy.redaksi.enabled): operator boleh mematikan redaksi jalur
     * provider (LLM lokal), tapi baris eskalasi dibaca manusia & berumur
     * panjang — komentar migrasi "SUDAH TERREDAKSI PII" wajib selalu benar.
     *
     * [M2] Idempoten per idempotency_key request (firstOrCreate): retry sah
     * atas request terblokir yang sama tidak menggandakan antrean ustadz.
     */
    private function tulisEskalasi(AiGenerateRequest $request, string $kategori): void
    {
        try {
            $maks = (int) config('ai.guardrails.eskalasi_ringkas_max', 300);
            $terredaksi = $this->redactor->redactPaksa($request->prompt)->text;

            $atribut = [
                'conversation_ref' => $request->idempotencyKey,
                'kanal' => $request->channel,
                'pertanyaan_ringkas' => mb_substr($terredaksi, 0, $maks),
                'kategori' => $kategori,
                'status' => AiEscalation::STATUS_BARU,
            ];

            if ($request->idempotencyKey !== null) {
                AiEscalation::query()->firstOrCreate(
                    ['idempotency_key' => $request->idempotencyKey],
                    $atribut,
                );
            } else {
                AiEscalation::query()->create($atribut);
            }
        } catch (Throwable $e) {
            Log::error('PolicyEngine: GAGAL menulis ai_escalation (Tanya Ustadz).', [
                'kategori' => $kategori,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function template(string $kunci): string
    {
        return (string) config(
            "ai.guardrails.pesan.{$kunci}",
            'Mohon maaf, permintaan Anda belum dapat kami proses. Silakan hubungi admin pesantren.',
        );
    }
}
