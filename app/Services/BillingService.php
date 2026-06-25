<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlatformInvoice;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantSubscription;
use Illuminate\Support\Collection;

class BillingService
{
    // ── Plans ────────────────────────────────────────────────────────────────

    public function createPlan(array $data): Plan
    {
        return Plan::create([
            'name' => $data['name'],
            'billing_type' => $data['billing_type'],
            'default_price' => $data['default_price'] ?? 0,
            'default_modules' => $data['default_modules'] ?? [],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    // ── Subscriptions ────────────────────────────────────────────────────────

    public function subscribe(Tenant $tenant, Plan $plan, array $overrides = []): TenantSubscription
    {
        // Nonaktifkan subscription lama jika ada
        TenantSubscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'override' => $overrides ?: null,
            'billing_cycle' => $overrides['billing_cycle'] ?? 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => null,
            'status' => 'active',
        ]);

        // Sync modul aktif sesuai plan (+ override)
        $this->syncModules($tenant, $subscription->effectiveModules());

        return $subscription;
    }

    public function suspendSubscription(TenantSubscription $subscription): TenantSubscription
    {
        $subscription->update(['status' => 'suspended']);

        return $subscription->fresh();
    }

    public function getActiveSubscription(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();
    }

    // ── Module Toggle (sesuai Blueprint §4 & §12) ─────────────────────────────

    public function syncModules(Tenant $tenant, array $modules): void
    {
        foreach ($modules as $moduleName) {
            TenantModule::updateOrCreate(
                ['tenant_id' => $tenant->id, 'module_name' => $moduleName],
                ['is_active' => true]
            );
        }
    }

    public function toggleModule(Tenant $tenant, string $moduleName, bool $active): TenantModule
    {
        return TenantModule::updateOrCreate(
            ['tenant_id' => $tenant->id, 'module_name' => $moduleName],
            ['is_active' => $active]
        );
    }

    // ── Invoice ──────────────────────────────────────────────────────────────

    public function generateInvoice(Tenant $tenant, string $period, ?float $amount = null): PlatformInvoice
    {
        $subscription = $this->getActiveSubscription($tenant);
        $effectiveAmount = $amount ?? $subscription?->effectivePrice() ?? 0;

        return PlatformInvoice::create([
            'tenant_id' => $tenant->id,
            'period' => $period,
            'amount' => $effectiveAmount,
            'status' => 'unpaid',
        ]);
    }

    public function getInvoicesForTenant(Tenant $tenant): Collection
    {
        return PlatformInvoice::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();
    }

    // ── MRR & Dashboard Stats ─────────────────────────────────────────────────

    public function getMRR(): float
    {
        return TenantSubscription::where('status', 'active')
            ->with('plan')
            ->get()
            ->sum(fn (TenantSubscription $s): float => $s->effectivePrice());
    }

    public function getStats(): array
    {
        return [
            'mrr' => $this->getMRR(),
            'active_tenants' => TenantSubscription::where('status', 'active')->count(),
            'unpaid_invoices' => PlatformInvoice::where('status', 'unpaid')->count(),
            'total_revenue' => PlatformInvoice::where('status', 'paid')->sum('amount'),
        ];
    }
}
