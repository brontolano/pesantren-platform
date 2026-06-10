<?php

namespace Modules\AI\Services\Settings;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Models\AiTenantSetting;
use Throwable;

/**
 * T-A010 — seed ai_tenant_setting versi 1 default KONSERVATIF untuk tenant
 * baru (Blueprint §8 butir 2). Status langsung 'aktif' TANPA approval:
 * default = kebijakan paling aman yang ada (santri OFF, edukatif-only,
 * scope minimum) — approval kyai dibutuhkan untuk PERUBAHAN sensitif,
 * bukan untuk titik awal teraman. Aktivasi runtime tetap digerbang
 * PolicyEngine (T-A020): Wizard tuntas + approval + saldo > 0.
 *
 * Dipanggil TenantProvisioner DI DALAM $tenant->run() (tabel tenant).
 * Idempoten & toleran — provisioning tenant tidak boleh gagal karena AI.
 */
class AiSettingProvisioner
{
    public function provision(string $tenantId): void
    {
        try {
            if (! Schema::hasTable('ai_tenant_setting')) {
                Log::warning('AiSettingProvisioner: tabel ai_tenant_setting belum termigrasi — dilewati.', [
                    'tenant_id' => $tenantId,
                ]);

                return;
            }

            // Idempoten: tenant yang sudah punya versi apa pun tidak disentuh
            // (reprovision TIDAK boleh menimpa hasil Wizard admin).
            if (AiTenantSetting::query()->exists()) {
                return;
            }

            AiTenantSetting::query()->create([
                'versi' => 1,
                'payload' => AiTenantSettingDefaults::konservatif(),
                'status' => AiTenantSetting::STATUS_AKTIF,
                'dibuat_oleh' => null,
                'ringkasan_perubahan' => 'Default konservatif provisioning tenant (T-A010).',
            ]);
        } catch (Throwable $e) {
            Log::warning('AiSettingProvisioner: gagal seed pengaturan AI default — provisioning tenant tetap lanjut.', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
