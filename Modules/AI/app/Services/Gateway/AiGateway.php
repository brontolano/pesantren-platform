<?php

namespace Modules\AI\Services\Gateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Services\Audit\AuditRecorder;
use Modules\AI\Services\Audit\StepTimer;
use Modules\AI\Services\Metering\BudgetGuard;
use Modules\AI\Services\Metering\CreditMeter;
use Throwable;

/**
 * SATU-SATUNYA pintu panggilan LLM di seluruh platform (Aturan Emas #4).
 *
 * Tanggung jawab:
 *  - meneruskan (agent, tier) ke ModelRouter → provider + model;
 *  - timeout (config), retry idempoten 1x HANYA untuk kegagalan KONEKSI
 *    (bukan timeout — hindari beban inferensi dobel di CPU 2 core);
 *  - circuit breaker per provider (Cache): sirkuit terbuka → langsung fallback;
 *  - fallback berantai ke provider berikutnya, lalu NullProvider — kegagalan
 *    PROVIDER tidak pernah menjadi exception bagi pemanggil, hanya degraded=true.
 *
 * Catatan kontrak: kesalahan PEMAKAIAN tetap melempar (fail-fast programmer
 * error) — tier tak dikenal (InvalidArgumentException dari ModelRouter) dan
 * config router/providers korup (RuntimeException/AiProviderException).
 */
class AiGateway
{
    public function __construct(
        private readonly ModelRouter $router,
        private readonly CircuitBreaker $breaker,
        private readonly AuditRecorder $audit,
        private readonly BudgetGuard $budgetGuard,
        private readonly CreditMeter $meter,
    ) {}

    public function generate(AiGenerateRequest $request): AiGenerateResult
    {
        // T-A003: setiap panggilan generate menulis tepat 1 baris ai_audit_log
        // (sukses / degradasi / gagal) dengan breakdown latensi per langkah.
        $timer = new StepTimer;

        // T-A002 pre-check anggaran: kill-switch aktif → balas santun "anggaran
        // habis" TANPA menyentuh provider (nol HTTP ke Ollama). Hanya berlaku
        // dalam konteks tenant — panggilan central (ai:health CLI) tak dimeter.
        $tenantId = tenancy()->initialized ? (string) tenant()?->getTenantKey() : null;

        if ($tenantId !== null) {
            $status = $timer->measure('budget', fn () => $this->budgetGuard->cekOrLog($tenantId));

            if ($status->killSwitch) {
                $result = new AiGenerateResult(
                    text: (string) config(
                        'ai.metering.kill_switch_message',
                        'Mohon maaf, anggaran AI bulan ini telah habis. Silakan hubungi admin untuk top-up kredit.',
                    ),
                    provider: 'null',
                    model: 'null',
                    degraded: true,
                );

                // Pagar #5: penolakan anggaran pun TETAP termeter (0 kredit).
                $meteran = $this->meterUsage($tenantId, $request, $result);
                $this->recordGenerateAudit($request, $timer, $result, meteran: $meteran);

                return $result;
            }
        }

        try {
            $selection = $timer->measure(
                'router',
                fn (): ModelSelection => $this->router->resolve($request->agent, $request->tier),
            );

            foreach ($this->chainFor($selection) as $name) {
                $result = $timer->measure('provider', fn (): ?AiGenerateResult => $this->attempt(
                    $name,
                    fn (LlmProvider $provider, ModelSelection $sel): AiGenerateResult => $provider->generate($request, $sel),
                    $selection,
                ));

                if ($result !== null) {
                    $meteran = $tenantId !== null ? $this->meterUsage($tenantId, $request, $result) : null;
                    $this->recordGenerateAudit($request, $timer, $result, meteran: $meteran);

                    return $result;
                }
            }

            // Semua provider nyata gagal/terbuka sirkuitnya → degradasi anggun.
            $result = $this->makeProvider('null')->generate($request, $selection);
            $meteran = $tenantId !== null ? $this->meterUsage($tenantId, $request, $result) : null;
            $this->recordGenerateAudit($request, $timer, $result, meteran: $meteran);

            return $result;
        } catch (Throwable $e) {
            // Kesalahan pemakaian (tier tak dikenal, config korup) tetap
            // dilempar — tapi jejaknya tercatat dulu untuk forensik.
            $this->recordGenerateAudit($request, $timer, null, $e);

            throw $e;
        }
    }

    /**
     * T-A002: meter satu pemakaian via CreditMeter (semua termeter — pagar #5;
     * degradasi = 0 kredit tapi tetap tercatat). Kunci idempotensi deterministik:
     * kunci request bila ada, fallback ULID unik per panggilan. Kegagalan
     * metering TIDAK menggagalkan permintaan utama (catatOrLog → log keras)
     * dan tercermin di audit (biaya 0 + penanda metering gagal).
     *
     * @return array{kredit: float, gagal: bool}
     */
    private function meterUsage(string $tenantId, AiGenerateRequest $request, AiGenerateResult $result): array
    {
        $kredit = $this->meter->hitungKredit(
            $request->tier,
            $result->promptTokens,
            $result->completionTokens,
            $result->degraded,
        );

        $ledger = $this->meter->catatOrLog($tenantId, [
            'agent_key' => $request->agent,
            'channel' => $request->channel,
            'provider' => $result->provider,
            'model' => $result->model,
            'tier' => $request->tier,
            'token_masuk' => $result->promptTokens,
            'token_keluar' => $result->completionTokens,
            'kredit' => $kredit,
            'degraded' => $result->degraded,
        ], $this->meteringKey($request, $result));

        // Pada duplikat, nilai tertagih = baris ledger lama (bukan hitungan
        // baru) supaya audit selalu rekonsiliasi dengan ledger.
        return [
            'kredit' => $ledger !== null ? abs((float) $ledger->jumlah_kredit) : 0.0,
            'gagal' => $ledger === null,
        ];
    }

    /**
     * Dedup idempotensi hanya boleh berlaku PER-OUTCOME: jalur 0-kredit
     * (kill-switch/degradasi) memakai namespace kunci sendiri. Tanpa ini,
     * retry sah atas request yang dulunya ditolak/degradasi ter-dedup
     * terhadap baris 0 kredit — token provider nyata terbakar tanpa tertagih.
     */
    private function meteringKey(AiGenerateRequest $request, AiGenerateResult $result): string
    {
        $base = $request->idempotencyKey ?? 'gen-'.Str::ulid();

        return $result->degraded ? 'degraded-'.$base : $base;
    }

    /**
     * Tulis baris audit T-A003. recordOrLog: kegagalan tulis audit TIDAK
     * menggagalkan permintaan utama (hanya log error keras).
     */
    /** @param  array{kredit: float, gagal: bool}|null  $meteran  Hasil meterUsage (T-A002). */
    private function recordGenerateAudit(
        AiGenerateRequest $request,
        StepTimer $timer,
        ?AiGenerateResult $result,
        ?Throwable $error = null,
        ?array $meteran = null,
    ): void {
        $status = match (true) {
            $error !== null => AiAuditLog::STATUS_GAGAL,
            $result?->degraded === true => AiAuditLog::STATUS_DEGRADASI,
            default => AiAuditLog::STATUS_SUKSES,
        };

        $this->audit->recordOrLog([
            'user_id' => $request->userId,
            'agent_key' => $request->agent,
            'channel' => $request->channel,
            'ringkasan_konteks' => $request->ringkasanKonteks,
            'provider' => $result->provider ?? 'tidak-terpilih',
            'model' => $result->model ?? 'tidak-terpilih',
            'tier' => $request->tier,
            'degraded' => $result->degraded ?? false,
            'token_masuk' => $result?->promptTokens,
            'token_keluar' => $result?->completionTokens,
            'biaya_kredit' => $meteran['kredit'] ?? 0, // T-A002 (CreditMeter)
            // Kegagalan metering tercermin di audit (kontrak T-A002).
            'keputusan_kebijakan' => ($meteran['gagal'] ?? false) ? ['metering' => 'gagal'] : null,
            'latency' => $timer->breakdown(),
            'status' => $status,
            'error_ringkas' => $error !== null
                ? mb_substr($error::class.': '.$error->getMessage(), 0, 500)
                : null,
            'idempotency_key' => $request->idempotencyKey,
        ]);
    }

    /**
     * @param  string|list<string>  $input
     * @return list<list<float>> Kosong = degradasi (tidak ada provider embedding sehat).
     */
    public function embed(string|array $input, string $agent = 'default'): array
    {
        $inputs = is_string($input) ? [$input] : array_values($input);

        if ($inputs === []) {
            return [];
        }

        $selection = $this->router->resolveEmbedding($agent);

        foreach ($this->chainFor($selection) as $name) {
            $result = $this->attempt(
                $name,
                fn (LlmProvider $provider, ModelSelection $sel): array => $provider->embed($inputs, $sel),
                $selection,
            );

            if ($result !== null) {
                return $result;
            }
        }

        Log::warning('AiGateway: semua provider embedding gagal — kembali kosong (degradasi).');

        return $this->makeProvider('null')->embed($inputs, $selection);
    }

    /** Status circuit breaker per provider nyata (dipakai ai:health). */
    public function circuitStates(): array
    {
        $states = [];

        foreach ($this->realProviderNames() as $name) {
            $states[$name] = $this->breaker->state("provider:{$name}");
        }

        return $states;
    }

    /** Laporan kesehatan tiap provider nyata (dipakai ai:health). */
    public function providerHealth(): array
    {
        $report = [];

        foreach ($this->realProviderNames() as $name) {
            $report[$name] = $this->makeProvider($name)->health();
        }

        return $report;
    }

    public function router(): ModelRouter
    {
        return $this->router;
    }

    /**
     * Satu percobaan ke satu provider: hormati circuit breaker, retry koneksi
     * 1x, catat sukses/gagal. null = gagal → lanjut provider berikutnya.
     *
     * @template T
     *
     * @param  callable(LlmProvider, ModelSelection): T  $call
     * @return T|null
     */
    private function attempt(string $name, callable $call, ModelSelection $selection): mixed
    {
        $breakerKey = "provider:{$name}";

        if (! $this->breaker->allowRequest($breakerKey)) {
            Log::info("AiGateway: sirkuit provider '{$name}' terbuka — dilewati, lanjut fallback.");

            return null;
        }

        $sel = $this->selectionFor($name, $selection);

        if ($sel === null) {
            Log::warning("AiGateway: provider fallback '{$name}' dilewati — tidak ada model default miliknya untuk tier '{$selection->tier}'.");

            return null;
        }

        $provider = $this->makeProvider($name);

        try {
            $result = $this->callWithConnectionRetry(fn () => $call($provider, $sel), $name);
            $this->breaker->recordSuccess($breakerKey);

            return $result;
        } catch (Throwable $e) {
            if ($this->isClientError($e)) {
                // HTTP 4xx = kesalahan konfigurasi/permintaan (mis. model belum
                // di-pull), BUKAN provider sakit — jangan racuni circuit breaker,
                // cukup log keras lalu lanjut fallback.
                Log::error("AiGateway: provider '{$name}' menolak permintaan (4xx) — periksa konfigurasi model.", [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            $this->breaker->recordFailure($breakerKey);
            Log::warning("AiGateway: provider '{$name}' gagal — fallback ke berikutnya.", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function isClientError(Throwable $e): bool
    {
        return $e instanceof AiProviderException
            && $e->statusCode !== null
            && $e->statusCode >= 400
            && $e->statusCode < 500;
    }

    /**
     * Retry idempoten: HANYA kegagalan koneksi murni (refused/DNS/reset) yang
     * diulang maksimal retry_connection_max kali. Timeout TIDAK diulang —
     * inferensi mungkin masih berjalan di server, mengulang = beban dobel.
     */
    private function callWithConnectionRetry(callable $call, string $providerName): mixed
    {
        $maxRetries = (int) config('ai.gateway.retry_connection_max', 1);

        for ($attempt = 0; ; $attempt++) {
            try {
                return $call();
            } catch (ConnectionException $e) {
                if ($attempt >= $maxRetries || ! $this->isRetryableConnectionError($e)) {
                    throw $e;
                }

                Log::info("AiGateway: kegagalan koneksi provider '{$providerName}', retry ke-".($attempt + 1).'.');
            }
        }
    }

    private function isRetryableConnectionError(ConnectionException $e): bool
    {
        $message = $e->getMessage();

        // Timeout BUKAN kandidat retry (cURL error 28 / "timed out").
        if (preg_match('/timed?\s?out|timeout|cURL error 28/i', $message)) {
            return false;
        }

        // HANYA kegagalan PRA-KIRIM yang aman di-retry: refused, DNS, SSL
        // handshake (cURL 6/7/35) — request belum sampai ke server, inferensi
        // pasti belum dimulai. Connection reset / empty reply / recv failure
        // (cURL 52/56) terjadi SETELAH request terkirim: inferensi mungkin
        // masih berjalan di server, retry = beban inferensi dobel → JANGAN.
        // Klasifikasi berbasis pesan memang rapuh terhadap variasi cURL/locale;
        // default saat tak dikenali = TIDAK retry (fail-safe).
        return (bool) preg_match(
            '/connection refused|failed to connect|could not resolve|couldn\'?t connect|'
            .'couldn\'?t resolve|cURL error (6|7|35)\b/i',
            $message,
        );
    }

    /**
     * Urutan provider yang dicoba: pilihan router lebih dulu, lalu sisa rantai
     * fallback config (tanpa duplikat). NullProvider tidak pernah masuk rantai
     * — ia jaring pengaman terakhir di generate()/embed().
     *
     * @return list<string>
     */
    private function chainFor(ModelSelection $selection): array
    {
        $chain = array_values(array_unique(array_merge(
            [$selection->provider],
            (array) config('ai.gateway.chain', []),
        )));

        return array_values(array_filter(
            $chain,
            fn (string $name): bool => $name !== 'null' && $this->providerClass($name) !== null,
        ));
    }

    /**
     * Model yang dipakai saat fallback ke provider LAIN: ambil model default
     * config tier terkait MILIK provider fallback itu. Model provider A tidak
     * boleh dikirim ke provider B (mis. baris config 'openai/gpt-4o' jatuh ke
     * Ollama → 404 model not found → breaker Ollama teracuni padahal sehat).
     * Tidak ada model milik provider fallback → null = provider dilewati.
     */
    private function selectionFor(string $providerName, ModelSelection $selection): ?ModelSelection
    {
        if ($providerName === $selection->provider) {
            return $selection;
        }

        $default = config("ai.router.defaults.{$selection->tier}");

        if (
            ! is_array($default)
            || ($default['provider'] ?? null) !== $providerName
            || ! isset($default['model'])
        ) {
            return null;
        }

        return new ModelSelection(
            provider: $providerName,
            model: (string) $default['model'],
            maxContextTokens: (int) ($default['max_context_tokens'] ?? 4096),
            options: (array) ($default['options'] ?? []),
            source: 'fallback-default',
            tier: $selection->tier,
        );
    }

    /** @return list<string> */
    private function realProviderNames(): array
    {
        return array_values(array_filter(
            array_keys((array) config('ai.gateway.providers', [])),
            static fn (string $name): bool => $name !== 'null',
        ));
    }

    private function providerClass(string $name): ?string
    {
        $class = config("ai.gateway.providers.{$name}");

        return is_string($class) && is_a($class, LlmProvider::class, true) ? $class : null;
    }

    private function makeProvider(string $name): LlmProvider
    {
        $class = $this->providerClass($name);

        if ($class === null) {
            throw new AiProviderException("Provider AI tidak terdaftar di config: '{$name}'.", provider: $name);
        }

        return app($class);
    }
}
