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
        // pemetaan (uji/internal) bebas.
        if ($keputusan = $this->cekFiturPerPeran($request, $payload)) {
            return $keputusan;
        }

        // (d) Blacklist topik: rule data (mirror setting + preset GATE-3).
        if ($keputusan = $this->cekBlacklist($request)) {
            return $keputusan;
        }

        // (e) Level fatwa: edukatif_only → pertanyaan berpola hukum dialihkan
        // ke Tanya Ustadz. informatif/khilafiyah lolos pre (sitasi = T-A022).
        if ($keputusan = $this->cekLevelFatwa($request, $payload)) {
            return $keputusan;
        }

        return PolicyDecision::lolos();
    }

    /**
     * Evaluasi post-response. v1 = pass-through; struktur disiapkan sebagai
     * hook injeksi disclaimer & penegakan sitasi (T-A022) — JANGAN tambah
     * logika blokir di sini tanpa memperbarui kontrak audit gateway.
     */
    public function post(AiGenerateResult $result, PolicyDecision $pre): PolicyDecision
    {
        // T-A022: di sinilah rule tipe 'disclaimer' kelak menyuntik teks
        // disclaimer ke jawaban. v1: keputusan pre diteruskan apa adanya.
        return $pre;
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
            // Agen tanpa pemetaan fitur (uji/internal) — bebas.
            return null;
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

    private function cekBlacklist(AiGenerateRequest $request): ?PolicyDecision
    {
        $rules = AiPolicyRule::query()
            ->where('tipe', AiPolicyRule::TIPE_BLACKLIST_TOPIK)
            ->where('aktif', true)
            ->whereHas('policy', fn ($q) => $q->where('aktif', true))
            ->orderBy('prioritas')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if ($this->rulePolaCocok($rule, $request->prompt)) {
                return $this->blokir(
                    $request,
                    kategori: 'blacklist_topik',
                    alasan: "prompt cocok rule blacklist #{$rule->getKey()}",
                    template: $rule->respons_template ?? $this->template('blacklist_topik'),
                    eskalasi: true,
                    ruleId: (int) $rule->getKey(),
                );
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    private function cekLevelFatwa(AiGenerateRequest $request, array $payload): ?PolicyDecision
    {
        if (($payload['level_fatwa'] ?? null) !== 'edukatif_only') {
            return null;
        }

        // Pola TEKNIS-LINGUISTIK pertanyaan hukum (config) — bukan daftar
        // topik agama (Aturan Emas #8).
        foreach ((array) config('ai.guardrails.pola_hukum', []) as $regex) {
            if (@preg_match((string) $regex, $request->prompt) === 1) {
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
            if (@preg_match((string) $regex, $prompt) === 1) {
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
     */
    private function tulisEskalasi(AiGenerateRequest $request, string $kategori): void
    {
        try {
            $maks = (int) config('ai.guardrails.eskalasi_ringkas_max', 300);
            $terredaksi = $this->redactor->redact($request->prompt)->text;

            AiEscalation::query()->create([
                'conversation_ref' => $request->idempotencyKey,
                'kanal' => $request->channel,
                'pertanyaan_ringkas' => mb_substr($terredaksi, 0, $maks),
                'kategori' => $kategori,
                'status' => AiEscalation::STATUS_BARU,
            ]);
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
