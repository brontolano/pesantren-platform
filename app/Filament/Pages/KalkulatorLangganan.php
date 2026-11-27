<?php

namespace App\Filament\Pages;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\BillingService;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\DB;

class KalkulatorLangganan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Kalkulator Langganan';

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Kalkulator Langganan';

    protected string $view = 'filament.pages.kalkulator-langganan';

    /** Modul yang bisa diaktifkan per tenant (key => label). */
    public const MODULES = [
        'ppdb' => 'PPDB',
        'kesantrian' => 'Kesantrian',
        'keuangan' => 'Keuangan',
        'akademik' => 'Akademik',
        'asrama' => 'Asrama',
        'tahfidz' => 'Tahfidz',
        'notifikasi' => 'Notifikasi WA',
        'portal_wali' => 'Portal Wali',
        'cms' => 'CMS Website',
    ];

    public ?array $data = [];

    public int $santriCount = 0;

    /** Fitur bisnis SaaS — HANYA panel platform (central 'admin'), bukan panel tenant. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->isPlatformSuperAdmin() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'billing_type' => 'per_santri',
            'price' => 5000,
            'billing_cycle' => 'monthly',
            'discount' => 0,
            'modules' => ['ppdb', 'kesantrian', 'keuangan', 'notifikasi'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()->schema([
                    Select::make('tenant_id')
                        ->label('Pesantren (tenant)')
                        ->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state) => $this->loadTenant($state)),
                    Select::make('template_plan')
                        ->label('Template paket')
                        ->options(fn () => ['' => 'Custom (kebijakan kerjasama khusus)'] + Plan::query()->pluck('name', 'id')->all())
                        ->default('')
                        ->live()
                        ->afterStateUpdated(fn ($state) => $this->applyTemplate($state)),
                ])->columns(2),

                Section::make()->schema([
                    Select::make('billing_type')
                        ->label('Tipe billing')
                        ->options([
                            'per_santri' => 'Per santri / bulan',
                            'flat' => 'Flat / bulan',
                            'custom' => 'Custom (nominal manual)',
                            'gratis' => 'Gratis',
                        ])
                        ->required()
                        ->live(),
                    TextInput::make('price')
                        ->label('Tarif (Rp)')
                        ->prefix('Rp')
                        ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                        ->stripCharacters('.')
                        ->live(onBlur: true)
                        ->disabled(fn (callable $get) => $get('billing_type') === 'gratis'),
                    Select::make('billing_cycle')
                        ->label('Siklus')
                        ->options(['monthly' => 'Bulanan', 'yearly' => 'Tahunan'])
                        ->required()
                        ->live(),
                ])->columns(3),

                Section::make()->schema([
                    TextInput::make('discount')
                        ->label('Diskon kerjasama (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->live(onBlur: true),
                    CheckboxList::make('modules')
                        ->label('Modul aktif (sesuai kesepakatan)')
                        ->options(self::MODULES)
                        ->columns(3)
                        ->live(),
                ]),
            ]);
    }

    public function loadTenant($tenantId): void
    {
        $this->santriCount = 0;
        if (! $tenantId) {
            return;
        }
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        // Jumlah santri aktif diambil dari DATABASE tenant (multi-db)
        $this->santriCount = (int) $tenant->run(fn () => DB::table('santri')->where('status', 'aktif')->count());

        // Muat langganan aktif bila ada
        $sub = app(BillingService::class)->getActiveSubscription($tenant);
        if ($sub) {
            $ov = $sub->override ?? [];
            $this->data['billing_type'] = $ov['billing_type'] ?? $sub->plan->billing_type ?? 'per_santri';
            $this->data['price'] = $ov['price'] ?? $sub->plan->default_price ?? 0;
            $this->data['billing_cycle'] = $sub->billing_cycle ?? 'monthly';
            $this->data['discount'] = $ov['discount'] ?? 0;
            $this->data['modules'] = $sub->effectiveModules();
        }
    }

    public function applyTemplate($planId): void
    {
        if (! $planId) {
            return;
        }
        $plan = Plan::find($planId);
        if (! $plan) {
            return;
        }
        $this->data['billing_type'] = $plan->billing_type;
        $this->data['price'] = (float) $plan->default_price;
        $this->data['modules'] = $plan->default_modules ?? [];
    }

    /** Tenant yang sedang dipilih (untuk header). */
    public function selectedTenant(): ?Tenant
    {
        return Tenant::with('domains')->find($this->data['tenant_id'] ?? null);
    }

    /** Bersihkan tarif dari pemisah ribuan ("5.000" => 5000). */
    protected function cleanPrice(): float
    {
        return (float) preg_replace('/\D/', '', (string) ($this->data['price'] ?? '0'));
    }

    /** Hitung pratinjau tagihan dari state sekarang. */
    public function previewData(): array
    {
        $type = $this->data['billing_type'] ?? 'per_santri';
        $price = $this->cleanPrice();
        $discount = (float) ($this->data['discount'] ?? 0);
        $cycle = $this->data['billing_cycle'] ?? 'monthly';

        $gross = match ($type) {
            'per_santri' => $this->santriCount * $price,
            'flat', 'custom' => $price,
            'gratis' => 0,
            default => 0,
        };
        if ($cycle === 'yearly') {
            $gross *= 12;
        }
        $total = $gross * (1 - $discount / 100);

        return [
            'count' => $this->santriCount,
            'type' => $type,
            'price' => $price,
            'discount' => $discount,
            'cycle' => $cycle === 'yearly' ? 'tahun' : 'bulan',
            'total' => max(0, round($total)),
        ];
    }

    protected function resolveTenantOrFail(): ?Tenant
    {
        $tenant = Tenant::find($this->data['tenant_id'] ?? null);
        if (! $tenant) {
            Notification::make()->title('Pilih pesantren dulu')->warning()->send();

            return null;
        }

        return $tenant;
    }

    /** Pastikan ada Plan (untuk FK subscription) — buat "Custom" bila perlu. */
    protected function ensurePlan(): Plan
    {
        $templateId = $this->data['template_plan'] ?? null;
        if ($templateId && $plan = Plan::find($templateId)) {
            return $plan;
        }

        return Plan::firstOrCreate(
            ['name' => 'Custom'],
            ['billing_type' => 'custom', 'default_price' => 0, 'default_modules' => [], 'is_active' => true]
        );
    }

    public function save(): void
    {
        $tenant = $this->resolveTenantOrFail();
        if (! $tenant) {
            return;
        }
        $plan = $this->ensurePlan();

        app(BillingService::class)->subscribe($tenant, $plan, [
            'billing_type' => $this->data['billing_type'] ?? 'per_santri',
            'price' => $this->cleanPrice(),
            'discount' => (float) ($this->data['discount'] ?? 0),
            'billing_cycle' => $this->data['billing_cycle'] ?? 'monthly',
            'modules' => $this->data['modules'] ?? [],
        ]);

        Notification::make()->title('Pengaturan langganan disimpan')->success()->send();
    }

    public function generateInvoice(): void
    {
        $tenant = $this->resolveTenantOrFail();
        if (! $tenant) {
            return;
        }
        $this->ensurePlan();
        // simpan dulu agar konsisten
        $this->save();

        $preview = $this->previewData();
        $period = now()->format('Y-m');

        app(BillingService::class)->generateInvoice($tenant, $period, (float) $preview['total']);

        Notification::make()
            ->title('Invoice dibuat')
            ->body('Periode '.$period.' — Rp '.number_format($preview['total'], 0, ',', '.'))
            ->success()
            ->send();
    }
}
