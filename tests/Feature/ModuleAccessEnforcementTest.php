<?php

namespace Tests\Feature;

use App\Filament\Resources\Kepengasuhan\PresensiAsrama\PresensiAsramaResource;
use App\Models\Tenant;
use App\Services\ModuleService;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Penegakan akses modul (keamanan fondasi): modul RELEASED yang nonaktif untuk
 * sebuah tenant TIDAK boleh terlihat/diakses; modul CORE selalu boleh. Plus
 * memastikan resource yang sebelumnya tanpa gerbang kini meng-override canAccess.
 */
class ModuleAccessEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_modul_released_nonaktif_ditolak_core_diizinkan(): void
    {
        $this->seed(ModuleRegistrySeeder::class);
        $svc = app(ModuleService::class);

        DB::table('tenants')->insert([
            'id' => 'pesx', 'name' => 'Pesantren X', 'is_active' => true,
            'data' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $tenant = new Tenant;
        $tenant->id = 'pesx'; // bukan tenant dev

        // asrama = released; belum ada baris tenant_modules → TIDAK terlihat.
        $this->assertFalse($svc->isVisibleForTenant('asrama', $tenant));

        // Aktifkan untuk tenant ini (lewat model → event membuang cache set modul) → terlihat.
        \App\Models\TenantModule::create([
            'tenant_id' => 'pesx', 'module_name' => 'asrama', 'is_active' => true,
        ]);
        $this->assertTrue($svc->isVisibleForTenant('asrama', $tenant));

        // Modul core selalu boleh, tanpa baris tenant_modules.
        $this->assertTrue($svc->isVisibleForTenant('kesantrian', $tenant));
    }

    public function test_presensi_asrama_resource_kini_override_can_access(): void
    {
        $ref = new ReflectionMethod(PresensiAsramaResource::class, 'canAccess');
        $this->assertSame(
            PresensiAsramaResource::class,
            $ref->getDeclaringClass()->getName(),
            'PresensiAsramaResource wajib override canAccess (gerbang modul), bukan warisan default.',
        );
    }
}
