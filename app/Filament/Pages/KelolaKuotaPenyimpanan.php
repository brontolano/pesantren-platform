<?php

namespace App\Filament\Pages;

use App\Models\MediaFile;
use App\Models\Tenant;
use App\Services\StorageQuotaService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Kelola kuota penyimpanan per tenant (CENTRAL only) — Fase 2 File Storage.
 * Super_admin set tier kuota (8/16/32 GB; 64/128/256 GB Soon → butuh object storage).
 */
class KelolaKuotaPenyimpanan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Kuota Penyimpanan';

    protected static string|\UnitEnum|null $navigationGroup = 'Pesantren';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Kuota Penyimpanan Tenant';

    protected string $view = 'filament.pages.kelola-kuota-penyimpanan';

    public ?array $data = [];

    public ?string $usageLabel = null;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->hasRole('super_admin') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['tenant_id' => null, 'quota_gb' => StorageQuotaService::DEFAULT_GB]);
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
                ]),
                Section::make('Kuota Penyimpanan')
                    ->visible(fn (callable $get) => filled($get('tenant_id')))
                    ->schema([
                        Placeholder::make('usage')
                            ->label('Pemakaian saat ini')
                            ->content(fn (): string => $this->usageLabel ?? '—'),
                        Radio::make('quota_gb')
                            ->label('Tier kuota')
                            ->options($this->tierOptions())
                            ->descriptions($this->tierDescriptions())
                            ->disableOptionWhen(fn ($value): bool => in_array((int) $value, StorageQuotaService::TIERS_SOON, true)
                                && ! StorageQuotaService::objectStorageReady())
                            ->required(),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    protected function tierOptions(): array
    {
        $opts = [];
        foreach (StorageQuotaService::TIERS_AVAILABLE as $gb) {
            $opts[$gb] = $gb.' GB';
        }
        foreach (StorageQuotaService::TIERS_SOON as $gb) {
            $opts[$gb] = $gb.' GB';
        }

        return $opts;
    }

    /** @return array<int, string> */
    protected function tierDescriptions(): array
    {
        $desc = [];
        foreach (StorageQuotaService::TIERS_AVAILABLE as $gb) {
            $desc[$gb] = 'Disk lokal';
        }
        foreach (StorageQuotaService::TIERS_SOON as $gb) {
            $desc[$gb] = StorageQuotaService::objectStorageReady() ? 'Object storage' : 'Soon — butuh object storage';
        }

        return $desc;
    }

    public function loadTenant($tenantId): void
    {
        $this->usageLabel = null;
        if (! $tenantId) {
            return;
        }
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        $svc = app(StorageQuotaService::class);
        $b = $tenant->run(fn () => $svc->breakdown());
        $this->data['quota_gb'] = $svc->quotaGb($tenantId);

        $quotaBytes = $svc->quotaBytes($tenantId);
        $footprint = (int) $b['footprint_bytes'];
        $pct = $quotaBytes > 0 ? round($footprint / $quotaBytes * 100, 1) : 0;
        $h = fn (int $x) => StorageQuotaService::humanBytes($x);

        // Footprint riil tenant (file disk + DB tenant) — murni data tenant,
        // sistem/modul/OS/tenant-lain tidak ikut terhitung.
        $this->usageLabel = $h($footprint).' dari '.$svc->quotaGb($tenantId).' GB ('.$pct.'%) '
            .'— file: '.$h((int) $b['disk_bytes']).' + DB: '.$h((int) $b['db_bytes'])
            .' (media tercatat: '.$h((int) $b['media_bytes']).')';
    }

    public function save(): void
    {
        $tenantId = $this->data['tenant_id'] ?? null;
        if (! $tenantId) {
            Notification::make()->title('Pilih pesantren dulu')->warning()->send();

            return;
        }

        $gb = (int) ($this->data['quota_gb'] ?? StorageQuotaService::DEFAULT_GB);

        if (in_array($gb, StorageQuotaService::TIERS_SOON, true) && ! StorageQuotaService::objectStorageReady()) {
            Notification::make()
                ->title('Tier belum tersedia')
                ->body('Tier '.$gb.' GB butuh object storage (Soon). Atur MEDIA_DISK=s3 + MEDIA_OBJECT_STORAGE=true dulu.')
                ->warning()->send();

            return;
        }

        app(StorageQuotaService::class)->setQuota($tenantId, $gb);
        $this->loadTenant($tenantId);

        Notification::make()->title('Kuota disimpan')->body('Tenant kini berkuota '.$gb.' GB.')->success()->send();
    }
}
