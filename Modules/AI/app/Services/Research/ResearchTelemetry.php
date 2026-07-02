<?php

namespace Modules\AI\Services\Research;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\AI\Models\AiResearchEvent;
use Modules\AI\Services\Settings\TenantSettingService;
use Throwable;

/**
 * T-A013 — telemetri riset produk BER-OPT-IN (PRD §6.4, pagar ADR-6).
 *
 * Prinsip kerja:
 *  - SEMUA method perekam adalah NO-OP SENYAP bila aktif() = false —
 *    tenant tanpa opt-in TIDAK menghasilkan satu pun baris (uji negatif
 *    PRD: nol event saat opt-out).
 *  - Perekaman best-effort: kegagalan tulis telemetri TIDAK PERNAH
 *    menggagalkan alur bisnis pemanggil (aktivasi setting, eskalasi).
 *  - payload HANYA agregat/kategori/kunci — tanpa isi percakapan, tanpa
 *    PII, tanpa identitas santri/wali. Skema per jenis terdokumentasi di
 *    model AiResearchEvent.
 *  - cabutIzin() menghapus SELURUH event tenant (hak cabut kapan saja).
 *
 * Binding SCOPED (AIServiceProvider) — memo opt-in benar-benar per
 * request/job, tidak nyangkut antar job di worker berumur panjang.
 */
class ResearchTelemetry
{
    /**
     * Cache ringan PER REQUEST status opt-in, dikunci per tenant aktif
     * (pola memo TenantSettingService).
     *
     * @var array{tenant: string, optin: bool}|null
     */
    private ?array $memo = null;

    /**
     * Apakah tenant ini opt-in telemetri riset? Dibaca dari payload
     * optin_riset pada setting AKTIF tenant. Fail-closed: tanpa konteks
     * tenant yang cocok / tanpa setting aktif = false.
     */
    public function aktif(string $tenantId): bool
    {
        if (! tenancy()->initialized || (string) tenant()->getKey() !== $tenantId) {
            return false;
        }

        if ($this->memo !== null && $this->memo['tenant'] === $tenantId) {
            return $this->memo['optin'];
        }

        // Resolusi FRESH (pola PolicyEngine) — jangan menyimpan instance
        // TenantSettingService bermemonya.
        $setting = app(TenantSettingService::class)->aktif();
        $optin = (bool) (((array) ($setting?->payload ?? []))['optin_riset'] ?? false);

        $this->memo = ['tenant' => $tenantId, 'optin' => $optin];

        return $optin;
    }

    /**
     * Hook lifecycle T-A010: dipanggil TenantSettingService SETELAH commit
     * setiap kali versi setting AKTIF berganti (ajukan non-sensitif /
     * putuskan disetujui).
     *
     *  - opt-in baru true  → rekam snapshot konfigurasi (potret pilihan
     *    Wizard yang baru berlaku);
     *  - opt-in baru false → bila sebelumnya true (atau tanpa pembanding —
     *    konservatif), hapus seluruh event tenant (cabut izin).
     *
     * @param  array<string, mixed>  $payloadBaru
     * @param  array<string, mixed>|null  $payloadLama  payload versi aktif
     *                                                  sebelumnya (null bila tidak ada — dianggap pernah opt-in agar
     *                                                  pembersihan tetap berjalan).
     */
    public function onSettingAktif(string $tenantId, array $payloadBaru, ?array $payloadLama): void
    {
        // Versi aktif berganti → memo opt-in pasti basi.
        $this->memo = null;

        $optinBaru = (bool) ($payloadBaru['optin_riset'] ?? false);
        $optinLama = (bool) (($payloadLama ?? ['optin_riset' => true])['optin_riset'] ?? false);

        if ($optinBaru) {
            $this->rekamSnapshotKonfigurasi($tenantId);
        } elseif ($optinLama) {
            $this->cabutIzin($tenantId);
        }
    }

    /**
     * Potret konfigurasi AKTIF tenant (dipanggil saat versi aktif berganti).
     * payload: preset_manhaj, level_fatwa, fitur_aktif (KUNCI saja),
     * mode_akses_santri, jumlah_scope_aktif — SENGAJA TANPA blacklist/
     * whitelist kustom (daftar buatan tenant bisa identifiable).
     */
    public function rekamSnapshotKonfigurasi(string $tenantId): void
    {
        if (! $this->aktif($tenantId)) {
            return;
        }

        try {
            $payload = (array) (app(TenantSettingService::class)->aktif()?->payload ?? []);

            if ($payload === []) {
                return;
            }

            $fiturAktif = [];
            foreach ((array) ($payload['fitur_per_peran'] ?? []) as $peran => $fitur) {
                foreach ((array) $fitur as $kunci => $nyala) {
                    if ($nyala) {
                        $fiturAktif[] = $peran.'.'.$kunci;
                    }
                }
            }
            sort($fiturAktif);

            $this->tulis($tenantId, AiResearchEvent::JENIS_SNAPSHOT_KONFIGURASI, [
                'preset_manhaj' => (string) ($payload['preset_manhaj'] ?? ''),
                'level_fatwa' => (string) ($payload['level_fatwa'] ?? ''),
                'fitur_aktif' => $fiturAktif,
                'mode_akses_santri' => (string) ($payload['mode_akses_santri'] ?? ''),
                'jumlah_scope_aktif' => count(array_filter((array) ($payload['scope_data'] ?? []))),
            ]);
        } catch (Throwable $e) {
            $this->logGagal('rekamSnapshotKonfigurasi', $tenantId, $e);
        }
    }

    /**
     * Distribusi KATEGORI eskalasi (BUKAN isi pertanyaan) — dipanggil dari
     * PolicyEngine/RagAnswerer saat baris ai_escalation BARU lahir.
     */
    public function rekamEskalasiKategori(string $tenantId, string $kategori): void
    {
        if (! $this->aktif($tenantId)) {
            return;
        }

        try {
            $this->tulis($tenantId, AiResearchEvent::JENIS_ESKALASI_KATEGORI, [
                // Kategori = identifier sistem; potong defensif agar tidak
                // pernah membawa teks panjang.
                'kategori' => mb_substr($kategori, 0, 64),
            ]);
        } catch (Throwable $e) {
            $this->logGagal('rekamEskalasiKategori', $tenantId, $e);
        }
    }

    /**
     * Rating kepuasan jawaban 1–5 per agen — API untuk UI nanti (T-A090).
     * Rating di luar 1–5 = kesalahan pemanggil (dilempar, bukan ditelan);
     * status opt-in tetap no-op senyap.
     */
    public function rekamRating(string $tenantId, string $agentKey, int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException(
                "Rating jawaban harus 1–5, diterima: {$rating}."
            );
        }

        if (! $this->aktif($tenantId)) {
            return;
        }

        try {
            $this->tulis($tenantId, AiResearchEvent::JENIS_RATING_JAWABAN, [
                'agent' => mb_substr($agentKey, 0, 64),
                'rating' => $rating,
            ]);
        } catch (Throwable $e) {
            $this->logGagal('rekamRating', $tenantId, $e);
        }
    }

    /**
     * Cabut izin riset: hapus SELURUH ai_research_event milik tenant ini
     * (hak cabut kapan saja, PRD §6.4 — alasan tabel TANPA trigger
     * append-only). Aliran baru otomatis berhenti karena aktif() membaca
     * optin_riset = false dari setting aktif.
     */
    public function cabutIzin(string $tenantId): void
    {
        $this->memo = null;

        try {
            AiResearchEvent::query()->where('tenant_id', $tenantId)->delete();
        } catch (Throwable $e) {
            // Kegagalan hapus = isu privasi — log keras untuk tindak lanjut,
            // tapi tetap tidak menggagalkan aktivasi setting pemanggil.
            Log::error('ResearchTelemetry: GAGAL menghapus event riset saat cabut izin (T-A013).', [
                'tenant_id' => $tenantId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     */
    private function tulis(string $tenantId, string $jenis, array $payload): void
    {
        AiResearchEvent::query()->create([
            'tenant_id' => $tenantId,
            'jenis' => $jenis,
            'payload' => $payload,
            'periode' => now()->startOfMonth()->toDateString(),
            'created_at' => now(),
        ]);
    }

    private function logGagal(string $method, string $tenantId, Throwable $e): void
    {
        // Best-effort: telemetri TIDAK boleh menggagalkan alur pemanggil.
        Log::warning("ResearchTelemetry: gagal {$method} (T-A013) — alur bisnis tetap berjalan.", [
            'tenant_id' => $tenantId,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
