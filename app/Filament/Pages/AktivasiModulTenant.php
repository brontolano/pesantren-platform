<?php

namespace App\Filament\Pages;

use App\Models\ModuleRegistry;
use App\Models\Tenant;
use App\Models\TenantModule;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Aktivasi modul per tenant (CENTRAL only).
 * Checklist modul Platform untuk tiap pesantren -> menulis ke tabel tenant_modules
 * (sumber kebenaran visibilitas modul "released" yang dibaca ModuleService).
 */
class AktivasiModulTenant extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationLabel = 'Aktivasi Modul Tenant';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing SaaS';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Aktivasi Modul per Tenant';

    protected string $view = 'filament.pages.aktivasi-modul-tenant';

    public ?array $data = [];

    /** Fitur SaaS — HANYA panel platform (central) + super_admin. */
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
        $this->form->fill(['tenant_id' => null, 'modules' => []]);
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
                        ->afterStateUpdated(fn ($state) => $this->loadActivations($state)),
                ]),
                Section::make('Modul Platform')
                    ->description('Centang modul yang aktif untuk tenant ini. Modul inti selalu aktif; modul development hanya untuk demo.')
                    ->schema($this->moduleToggles())
                    ->columns(2)
                    ->visible(fn (callable $get) => filled($get('tenant_id'))),
            ]);
    }

    /** @return array<int, Toggle> */
    protected function moduleToggles(): array
    {
        return ModuleRegistry::query()->orderBy('sort')->get()->map(function (ModuleRegistry $m): Toggle {
            $toggle = Toggle::make("modules.{$m->key}")->label($m->label)->inline(false);

            if ($m->is_core) {
                return $toggle->disabled()->helperText('🔒 Inti — selalu aktif');
            }
            if ($m->status === 'development') {
                return $toggle->disabled()->helperText('🟠 Development — hanya demo (set Released dulu di Modul Platform)');
            }

            return $toggle->helperText('🟢 Released — aktif/nonaktif per tenant');
        })->all();
    }

    public function loadActivations($tenantId): void
    {
        $modules = [];
        foreach (ModuleRegistry::query()->get() as $m) {
            if ($m->is_core) {
                $modules[$m->key] = true;
            } elseif ($m->status === 'development') {
                $modules[$m->key] = false;
            } else {
                $modules[$m->key] = TenantModule::isModuleActive($tenantId, $m->key);
            }
        }
        $this->data['modules'] = $modules;
    }

    public function save(): void
    {
        $tenantId = $this->data['tenant_id'] ?? null;
        if (! $tenantId) {
            Notification::make()->title('Pilih pesantren dulu')->warning()->send();

            return;
        }

        $changed = 0;
        foreach (ModuleRegistry::query()->get() as $m) {
            if ($m->is_core || $m->status === 'development') {
                continue; // core selalu aktif; development hanya demo
            }
            TenantModule::updateOrCreate(
                ['tenant_id' => $tenantId, 'module_name' => $m->key],
                ['is_active' => (bool) ($this->data['modules'][$m->key] ?? false)],
            );
            $changed++;
        }

        Notification::make()
            ->title('Aktivasi modul disimpan')
            ->body("{$changed} modul released disinkronkan untuk tenant ini.")
            ->success()
            ->send();
    }
}
