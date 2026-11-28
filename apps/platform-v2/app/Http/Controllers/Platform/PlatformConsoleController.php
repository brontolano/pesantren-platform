<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\ModuleCatalogItem;
use App\Models\Organization;
use App\Models\PlatformOperator;
use App\Services\Platform\TechnicalMonitor;
use App\Services\Provisioning\CreateTenantDraft;
use App\Services\Provisioning\TenantProvisioningService;
use App\Support\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlatformConsoleController extends Controller
{
    public function dashboard(): View
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(6)->startOfDay();
        $end = $today->copy()->endOfDay();

        // Dashboard aggregates stay on the central registry; tenant databases are never queried here.
        $summary = Organization::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN setup_state = 'failed' THEN 1 ELSE 0 END) as failed")
            ->first();

        $activityByDay = AuditEvent::query()
            ->selectRaw('DATE(created_at) as activity_day, COUNT(*) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'activity_day');

        $trend = collect(range(0, 6))->map(function (int $offset) use ($start, $activityByDay): array {
            $day = $start->copy()->addDays($offset);
            $key = $day->toDateString();

            return ['date' => $key, 'label' => $day->translatedFormat('D'), 'count' => (int) ($activityByDay[$key] ?? 0)];
        });

        return view('platform.dashboard', [
            'counts' => [
                'total' => (int) ($summary->total ?? 0),
                'failed' => (int) ($summary->failed ?? 0),
                'modules' => ModuleCatalogItem::query()->where('available', true)->count(),
            ],
            'trend' => $trend,
            'trendMax' => max(1, (int) $trend->max('count')),
            'recent' => AuditEvent::query()->select(['id', 'event', 'actor_id', 'organization_id', 'created_at'])->latest('created_at')->limit(5)->get(),
        ]);
    }

    public function organizations(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'service_state' => ['nullable', 'in:inactive,active,suspended'],
            'module_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $modules = ModuleCatalogItem::query()->where('available', true)->orderBy('name')->get(['id', 'name']);

        $query = Organization::query()->when(trim((string) ($filters['q'] ?? '')) !== '', function ($builder) use ($filters): void {
            $term = '%'.trim($filters['q']).'%';
            $builder->where(fn ($nested) => $nested->where('name', 'ilike', $term)->orWhere('canonical_domain', 'ilike', $term));
        })->when(! empty($filters['service_state']), fn ($builder) => $builder->where('service_state', $filters['service_state']))
            ->when(! empty($filters['module_id']) && $modules->contains('id', (int) $filters['module_id']), function ($builder) use ($filters): void {
                $builder->whereHas('moduleEntitlements', fn ($entitlements) => $entitlements->where('module_catalog_item_id', (int) $filters['module_id']));
            });

        return view('platform.organizations', [
            'organizations' => $query->latest('id')->paginate(15)->withQueryString(),
            'modules' => $modules,
            'filters' => $filters,
        ]);
    }

    public function storeDraft(Request $request, CreateTenantDraft $drafts): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'organization_type' => ['required', 'in:pesantren,yayasan,organisasi_islam,majelis_taklim'],
            'canonical_domain' => ['required', 'string', 'max:253'],
            'first_admin_email' => ['required', 'email', 'max:190'],
        ], [
            'name.required' => 'Isi nama organisasi.',
            'organization_type.required' => 'Pilih jenis organisasi.',
            'first_admin_email.email' => 'Masukkan alamat email yang valid.',
        ]);

        $draft = $drafts->handle($input);

        return redirect()->route('platform.organizations.index')->with('success', 'Draf organisasi tersimpan. Lanjutkan penyiapan dari daftar organisasi.')->with('created_organization_id', $draft->id);
    }

    public function setup(Organization $organization, Request $request, TenantProvisioningService $provisioning): RedirectResponse
    {
        abort_unless(in_array($organization->setup_state, ['draft', 'failed', 'pending', 'running'], true), 409, 'Status penyiapan ini tidak dapat dilanjutkan.');
        $operator = $request->user('platform');
        abort_unless($operator instanceof PlatformOperator, 403);

        $result = $provisioning->retry($organization, (string) $organization->provisioning_key, $operator);
        if ($result->setup_state !== 'ready') {
            return back()->with('error', 'Penyiapan belum selesai. Periksa langkah pemulihan lalu lanjutkan organisasi yang sama.');
        }

        return back()->with('success', 'Tenant siap. Undangan admin pertama dikirim ke penampung email yang dikonfigurasi.');
    }

    public function updateServiceState(Organization $organization, Request $request, PlatformAudit $audit): RedirectResponse
    {
        $data = $request->validate(['service_state' => ['required', 'in:inactive,active,suspended']]);
        abort_if($data['service_state'] === 'active' && $organization->setup_state !== 'ready', 409, 'Lanjutkan penyiapan tenant sebelum mengaktifkan layanan.');
        $previous = $organization->service_state;
        $organization->forceFill(['service_state' => $data['service_state']])->save();
        $audit->record('organization.service_state_changed', $organization->id, ['from' => $previous, 'to' => $organization->service_state], Organization::class, $organization->id);

        return back()->with('success', 'Status layanan organisasi diperbarui.');
    }

    public function check(Organization $organization, TechnicalMonitor $monitor): RedirectResponse
    {
        $result = $monitor->check($organization);

        return back()->with($result->state === 'healthy' ? 'success' : 'error', 'Pemeriksaan teknis: '.$result->state.'. '.$result->recovery_step);
    }

    public function modules(): View
    {
        return view('platform.modules', ['modules' => ModuleCatalogItem::query()->orderBy('name')->get()]);
    }

    public function activity(): View
    {
        return view('platform.activity', ['events' => AuditEvent::query()->latest('created_at')->paginate(25)]);
    }
}
