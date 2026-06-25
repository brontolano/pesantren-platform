<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformInvoice;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantSubscription;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    private Tenant $tenant;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = new BillingService;
        $this->tenant = Tenant::create(['id' => 'pesantren-a', 'name' => 'Pesantren A', 'is_active' => true]);
        $this->plan = Plan::create([
            'name' => 'Paket Standar',
            'billing_type' => 'flat',
            'default_price' => 500000,
            'default_modules' => ['Kesantrian', 'Keuangan', 'CMS'],
            'is_active' => true,
        ]);
    }

    public function test_subscribe_tenant_ke_plan(): void
    {
        $sub = $this->billing->subscribe($this->tenant, $this->plan);

        $this->assertInstanceOf(TenantSubscription::class, $sub);
        $this->assertEquals('active', $sub->status);
        $this->assertEquals($this->plan->id, $sub->plan_id);
        $this->assertEquals(500000.0, $sub->effectivePrice());
    }

    public function test_override_harga_per_tenant(): void
    {
        $sub = $this->billing->subscribe($this->tenant, $this->plan, [
            'price' => 350000,
        ]);

        $this->assertEquals(350000.0, $sub->effectivePrice());
    }

    public function test_subscribe_baru_membatalkan_yang_lama(): void
    {
        $sub1 = $this->billing->subscribe($this->tenant, $this->plan);
        $sub2 = $this->billing->subscribe($this->tenant, $this->plan);

        $sub1->refresh();
        $this->assertEquals('cancelled', $sub1->status);
        $this->assertEquals('active', $sub2->status);
    }

    public function test_toggle_modul_aktif(): void
    {
        $this->billing->toggleModule($this->tenant, 'Tahfidz', true);

        $module = TenantModule::where('tenant_id', $this->tenant->id)
            ->where('module_name', 'Tahfidz')
            ->first();

        $this->assertNotNull($module);
        $this->assertTrue($module->is_active);
    }

    public function test_toggle_modul_nonaktif(): void
    {
        $this->billing->toggleModule($this->tenant, 'Asrama', true);
        $this->billing->toggleModule($this->tenant, 'Asrama', false);

        $module = TenantModule::where('tenant_id', $this->tenant->id)
            ->where('module_name', 'Asrama')
            ->first();

        $this->assertFalse($module->is_active);
    }

    public function test_generate_invoice_pakai_harga_efektif(): void
    {
        $this->billing->subscribe($this->tenant, $this->plan);
        $invoice = $this->billing->generateInvoice($this->tenant, '2026-06');

        $this->assertInstanceOf(PlatformInvoice::class, $invoice);
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertEquals(500000.0, (float) $invoice->amount);
        $this->assertEquals('2026-06', $invoice->period);
    }

    public function test_invoice_bisa_ditandai_lunas(): void
    {
        $invoice = $this->billing->generateInvoice($this->tenant, '2026-06', 300000);
        $invoice->markPaid('proofs/bukti.jpg');

        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_mrr_dihitung_dari_subscription_aktif(): void
    {
        $tenantB = Tenant::create(['id' => 'pesantren-b', 'name' => 'Pesantren B', 'is_active' => true]);

        $this->billing->subscribe($this->tenant, $this->plan);
        $this->billing->subscribe($tenantB, $this->plan, ['price' => 400000]);

        $mrr = $this->billing->getMRR();

        $this->assertEquals(900000.0, $mrr); // 500000 + 400000
    }

    public function test_stats_aggregate_benar(): void
    {
        $this->billing->subscribe($this->tenant, $this->plan);
        $this->billing->generateInvoice($this->tenant, '2026-05', 500000);

        $stats = $this->billing->getStats();

        $this->assertEquals(1, $stats['active_tenants']);
        $this->assertEquals(1, $stats['unpaid_invoices']);
        $this->assertEquals(500000.0, $stats['mrr']);
    }
}
