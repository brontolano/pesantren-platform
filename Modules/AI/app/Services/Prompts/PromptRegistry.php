<?php

namespace Modules\AI\Services\Prompts;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\AI\Models\AiAgent;
use Modules\AI\Models\AiPromptTenantOverride;
use Modules\AI\Models\AiPromptVersion;
use Modules\AI\Services\Gateway\CentralCache;

/**
 * T-A004 — Prompt registry ber-versi & feature flag per tenant.
 *
 * Urutan resolusi resolve(agentKey, tenantId):
 *  1. Override tenant: enabled=false → agen mati untuk tenant ini;
 *     prompt_version_id terisi → pin versi itu (menang atas versi aktif).
 *  2. Versi 'aktif' agen (satu-satunya — partial unique index).
 *  3. Fallback: tanpa system prompt (gateway tetap jalan; agen belum terdaftar
 *     atau tabel belum termigrasi tidak boleh mematikan gateway).
 *
 * Cache: CentralCache (kebal CacheTenancyBootstrapper) TTL 60 dtk + invalidasi
 * eksplisit. Aktivasi/rollback memengaruhi SEMUA tenant tanpa pin — dipakai
 * stempel generasi per agen: aktivasi menaikkan generasi → seluruh key lama
 * (semua tenant) otomatis tak terpakai, tanpa perlu enumerasi tenant.
 */
class PromptRegistry
{
    private const CACHE_PREFIX = 'ai:prompt:';

    public function resolve(string $agentKey, ?string $tenantId = null): PromptSelection
    {
        $ttl = (int) config('ai.prompts.cache_seconds', 60);

        $key = $this->cacheKey($agentKey, $tenantId);
        $cached = CentralCache::repository()->remember(
            $key,
            $ttl,
            fn (): array => $this->lookup($agentKey, $tenantId)->toArray(),
        );

        // Entri lama/korup = cache-miss (lihat ModelSelection::toArray).
        if (! is_array($cached)) {
            CentralCache::repository()->forget($key);
            $cached = $this->lookup($agentKey, $tenantId)->toArray();
        }

        return PromptSelection::fromArray($cached);
    }

    /** Hapus cache satu kombinasi agen+tenant (dipakai saat override berubah). */
    public function forget(string $agentKey, ?string $tenantId = null): void
    {
        CentralCache::repository()->forget($this->cacheKey($agentKey, $tenantId));
    }

    /**
     * Aktifkan satu versi: transaksi central — versi aktif lama diarsipkan,
     * target jadi 'aktif'. Berlaku langsung (tanpa deploy) untuk semua tenant
     * tanpa pin: generasi cache agen dinaikkan.
     */
    public function aktifkanVersi(string $agentKey, int $versi): AiPromptVersion
    {
        $central = (string) config('tenancy.database.central_connection');

        $target = DB::connection($central)->transaction(function () use ($agentKey, $versi): AiPromptVersion {
            $target = AiPromptVersion::query()
                ->where('agent_key', $agentKey)
                ->where('versi', $versi)
                ->lockForUpdate()
                ->first();

            if ($target === null) {
                throw new InvalidArgumentException(
                    "Versi prompt {$versi} untuk agen '{$agentKey}' tidak ditemukan.",
                );
            }

            // Arsipkan versi aktif lama DULU (partial unique index: tidak boleh
            // ada dua 'aktif' bersamaan walau sesaat).
            AiPromptVersion::query()
                ->where('agent_key', $agentKey)
                ->where('status', AiPromptVersion::STATUS_AKTIF)
                ->where('id', '!=', $target->id)
                ->update(['status' => AiPromptVersion::STATUS_ARSIP, 'updated_at' => now()]);

            $target->status = AiPromptVersion::STATUS_AKTIF;
            $target->save(); // guard model: status boleh berubah, isi tidak.

            return $target;
        });

        $this->bumpGeneration($agentKey);

        return $target;
    }

    /**
     * Rollback = aktifkan kembali versi lama (semantik identik aktifkanVersi,
     * dipisah demi keterbacaan niat operator di pemanggil/CLI).
     */
    public function rollbackKeVersi(string $agentKey, int $versi): AiPromptVersion
    {
        return $this->aktifkanVersi($agentKey, $versi);
    }

    /**
     * Pasang/ubah override tenant (pin versi dan/atau feature flag) +
     * invalidasi cache kombinasi terkait.
     */
    public function setOverride(
        string $tenantId,
        string $agentKey,
        ?int $promptVersionId = null,
        bool $enabled = true,
    ): AiPromptTenantOverride {
        $override = AiPromptTenantOverride::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'agent_key' => $agentKey],
            ['prompt_version_id' => $promptVersionId, 'enabled' => $enabled],
        );

        $this->forget($agentKey, $tenantId);

        return $override;
    }

    /**
     * Buat versi baru (status draft) — isi versi lama TIDAK pernah diedit;
     * nomor versi = maksimum + 1.
     */
    public function buatVersi(
        string $agentKey,
        string $systemPrompt,
        ?string $changelog = null,
        ?int $createdBy = null,
    ): AiPromptVersion {
        $versi = (int) AiPromptVersion::query()->where('agent_key', $agentKey)->max('versi') + 1;

        return AiPromptVersion::query()->create([
            'agent_key' => $agentKey,
            'versi' => $versi,
            'system_prompt' => $systemPrompt,
            'changelog' => $changelog,
            'status' => AiPromptVersion::STATUS_DRAFT,
            'created_by' => $createdBy,
        ]);
    }

    /** Apakah agen terdaftar (dipakai ai:health memilih agen uji). */
    public function agentTerdaftar(string $agentKey): bool
    {
        try {
            return AiAgent::query()->where('key', $agentKey)->where('is_active', true)->exists();
        } catch (QueryException) {
            return false; // tabel belum termigrasi → anggap belum ada.
        }
    }

    private function lookup(string $agentKey, ?string $tenantId): PromptSelection
    {
        try {
            if ($tenantId !== null) {
                $override = AiPromptTenantOverride::query()
                    ->where('tenant_id', $tenantId)
                    ->where('agent_key', $agentKey)
                    ->first();

                if ($override !== null && ! $override->enabled) {
                    return new PromptSelection(null, null, enabled: false, source: 'disabled');
                }

                if ($override?->prompt_version_id !== null) {
                    $pinned = AiPromptVersion::query()->find($override->prompt_version_id);

                    if ($pinned !== null) {
                        return new PromptSelection(
                            $pinned->system_prompt,
                            (int) $pinned->id,
                            source: 'override-pin',
                        );
                    }
                }
            }

            $aktif = AiPromptVersion::query()
                ->where('agent_key', $agentKey)
                ->where('status', AiPromptVersion::STATUS_AKTIF)
                ->first();

            if ($aktif !== null) {
                return new PromptSelection($aktif->system_prompt, (int) $aktif->id, source: 'aktif');
            }
        } catch (QueryException) {
            // Tabel belum termigrasi (lingkungan lama) → fallback tanpa prompt.
        }

        return new PromptSelection(null, null, source: 'fallback');
    }

    /** Key cache ber-stempel generasi per agen (lihat doc kelas). */
    private function cacheKey(string $agentKey, ?string $tenantId): string
    {
        return self::CACHE_PREFIX.$this->generation($agentKey).':'.$agentKey.':'.($tenantId ?? '_central');
    }

    private function generation(string $agentKey): int
    {
        return (int) CentralCache::repository()->get(self::CACHE_PREFIX.'gen:'.$agentKey, 0);
    }

    private function bumpGeneration(string $agentKey): void
    {
        $key = self::CACHE_PREFIX.'gen:'.$agentKey;
        $repo = CentralCache::repository();
        $repo->forever($key, $this->generation($agentKey) + 1);
    }
}
