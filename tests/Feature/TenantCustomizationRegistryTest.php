<?php

namespace Tests\Feature;

use App\Support\TenantCustomizationRegistry;
use Illuminate\Support\Facades\Config;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\BroadcastWaResource;
use Modules\PPDB\Filament\Resources\CalonSantriResource;
use Modules\PPDB\Filament\Resources\TagihanPpdbResource;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Guard refactor #343 tahap 2: gating fitur + label override via
 * TenantCustomizationRegistry harus berperilaku IDENTIK dengan cabang
 * `AlbaKidsTenant::isActive()` lama di resource PPDB & Notifikasi —
 * tenant biasa memakai default, tenant dengan entri config (albakids)
 * memakai override.
 *
 * Pakai createRuntimeTenant (pola TenantPanelAuthedSmokeTest); entri config
 * disuntik untuk tenant-key runtime agar tidak tergantung DB tenant fisik
 * 'tenantalbakids'.
 */
class TenantCustomizationRegistryTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** Salin entri albakids dari config ke tenant-key runtime (simulasi tenant white-label). */
    protected function applyAlbaKidsEntryFor(string $tenantKey): void
    {
        $entry = (array) config('tenant-customization.tenants.albakids');
        unset($entry['hosts']);
        Config::set('tenant-customization.tenants.'.$tenantKey, $entry);
    }

    public function test_tenant_biasa_memakai_default_feature_label_dan_scope(): void
    {
        $tenant = $this->createRuntimeTenant('reg-def', 'Pesantren Default');
        tenancy()->initialize($tenant);

        $this->assertFalse(TenantCustomizationRegistry::feature('ppdb_custom_flow'));
        $this->assertNull(TenantCustomizationRegistry::stringValue('ppdb_gelombang_scope'));
        $this->assertNull(TenantCustomizationRegistry::stringValue('ppdb_tagihan_tipe_scope'));
        $this->assertNull(TenantCustomizationRegistry::stringValue('notif_template_prefix'));
        $this->assertSame('pending', TenantCustomizationRegistry::stringValue('ppdb_status_default'));
        $this->assertSame(
            ['Reguler' => 'Reguler', 'Tahfidz' => 'Tahfidz', 'Muadalah' => 'Muadalah'],
            (array) TenantCustomizationRegistry::value('ppdb_program_options')
        );

        // Label default resource (perilaku lama cabang "bukan albakids").
        $this->assertSame('Calon Santri', CalonSantriResource::getNavigationLabel());
        $this->assertSame('Pendaftaran', CalonSantriResource::getNavigationGroup());
        $this->assertSame('Tagihan PPDB', TagihanPpdbResource::getNavigationLabel());
        $this->assertSame('Broadcast WA', BroadcastWaResource::getNavigationLabel());
        $this->assertSame('Komunikasi', BroadcastWaResource::getNavigationGroup());

        tenancy()->end();
    }

    public function test_tenant_dengan_entri_albakids_memakai_override_identik_perilaku_lama(): void
    {
        $tenant = $this->createRuntimeTenant('reg-ak', 'Alba Kids Pre-School');
        tenancy()->initialize($tenant);
        $this->applyAlbaKidsEntryFor((string) $tenant->getTenantKey());

        // Gating fitur (dulu: AlbaKidsTenant::isActive()).
        $this->assertTrue(TenantCustomizationRegistry::feature('ppdb_custom_flow'));
        $this->assertFalse(CalonSantriResource::canCreate(), 'Alur custom: panitia tidak membuat pendaftar manual');

        // Scope query & opsi form.
        $this->assertSame('Landing Alba Kids', TenantCustomizationRegistry::stringValue('ppdb_gelombang_scope'));
        $this->assertSame('pendaftaran_albakids', TenantCustomizationRegistry::stringValue('ppdb_tagihan_tipe_scope'));
        $this->assertSame('albakids_', TenantCustomizationRegistry::stringValue('notif_template_prefix'));
        $this->assertSame('menunggu_verifikasi', TenantCustomizationRegistry::stringValue('ppdb_status_default'));
        $this->assertSame(['Preschool' => 'Preschool'], (array) TenantCustomizationRegistry::value('ppdb_program_options'));

        // Label override (dulu: ternary hardcoded di tiap resource).
        $this->assertSame('Daftar Pendaftar', CalonSantriResource::getNavigationLabel());
        $this->assertSame('Pendaftar Alba Kids', CalonSantriResource::getModelLabel());
        $this->assertSame('PPDB Custom', CalonSantriResource::getNavigationGroup());
        $this->assertSame('Custom Form', TagihanPpdbResource::getNavigationLabel());
        $this->assertSame('Form Website Alba Kids', TagihanPpdbResource::getModelLabel());
        $this->assertSame('Broadcast Pesan', BroadcastWaResource::getNavigationLabel());
        $this->assertSame('Notifikasi Custom', BroadcastWaResource::getNavigationGroup());

        tenancy()->end();
    }

    public function test_label_fallback_bila_kunci_tak_dioverride(): void
    {
        $tenant = $this->createRuntimeTenant('reg-fb', 'Pesantren Fallback');
        tenancy()->initialize($tenant);

        Config::set('tenant-customization.tenants.'.$tenant->getTenantKey(), [
            'labels' => ['ppdb.calon_santri.navigation' => 'Pendaftar Baru'],
        ]);

        $this->assertSame('Pendaftar Baru', TenantCustomizationRegistry::label('ppdb.calon_santri.navigation', 'Calon Santri'));
        // Kunci lain tidak dioverride → default parameter.
        $this->assertSame('Calon Santri', TenantCustomizationRegistry::label('ppdb.calon_santri.model', 'Calon Santri'));
        $this->assertSame('Pendaftaran', CalonSantriResource::getNavigationGroup());

        tenancy()->end();
    }
}
