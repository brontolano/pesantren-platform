<?php

namespace App\Services\Platform;

use App\Models\Organization;
use App\Models\TechnicalCheck;
use App\Support\PlatformAudit;
use App\Services\Tenancy\TenantConnection;
use Illuminate\Support\Facades\DB;

class TechnicalMonitor
{
    public function __construct(private readonly PlatformAudit $audit, private readonly TenantConnection $tenantConnection) {}

    public function check(Organization $organization): TechnicalCheck
    {
        $state = 'failed';
        $failure = 'tenant_database_unavailable';
        $recovery = 'Lanjutkan setup organisasi untuk memeriksa database tenant.';

        if ($organization->setup_state === 'ready' && $organization->tenant_database_name) {
            try {
                $this->tenantConnection->forOrganization($organization);
                DB::connection('tenant')->selectOne('SELECT 1');
                $state = 'healthy';
                $failure = null;
                $recovery = 'Tidak ada tindakan yang diperlukan.';
            } catch (\Throwable) {
                $failure = 'tenant_database_probe_failed';
                $recovery = 'Periksa ketersediaan database tenant dan jalankan kembali pemeriksaan.';
            }
        }

        $check = TechnicalCheck::query()->create([
            'organization_id' => $organization->id,
            'check_key' => 'tenant_database',
            'state' => $state,
            'checked_at' => now(),
            'failure_code' => $failure,
            'recovery_step' => $recovery,
        ]);
        $organization->forceFill(['last_technical_check_at' => $check->checked_at, 'last_technical_check_state' => $state])->save();
        $this->audit->record('organization.technical_check', $organization->id, ['state' => $state, 'failure_code' => $failure], TechnicalCheck::class, $check->id);

        return $check;
    }
}
