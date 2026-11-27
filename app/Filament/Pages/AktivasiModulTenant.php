<?php

namespace App\Filament\Pages;

use App\Models\ModuleRegistry;
use App\Models\Tenant;
use App\Models\TenantModule;
use Filament\Actions\Action;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Pesantren';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Aktivasi Modul per Tenant';

    protected string $view = 'filament.pages.aktivasi-modul-tenant';

    public ?array $data = [];

    /** Fitur SaaS — HANYA panel platform (central) + super_admin. */
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
                        ->placeholder('Pilih pesantren dulu untuk menampilkan modulnya')
                        ->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state) => $this->loadActivations($state)),
                ]),
                Section::make('Modul Opsional')
                    ->description('Aktif/nonaktif per pesantren sesuai kebijakan & paket langganan. Centang untuk mengaktifkan, lalu tekan "Simpan Aktivasi" untuk menerapkan.')
                    ->schema($this->moduleToggles('optional'))
                    ->columns(2)
                    ->visible(fn (callable $get) => filled($get('tenant_id'))),
                Section::make('Modul Inti')
                    ->description('Selalu aktif untuk semua pesantren — tidak dapat dimatikan.')
                    ->schema($this->moduleToggles('core'))
                    ->columns(2)
                    ->collapsed()
                    ->visible(fn (callable $get) => filled($get('tenant_id'))),
                Section::make('Modul Pengembangan')
                    ->description('Masih uji coba — hanya tampil di tenant demo. Ubah statusnya jadi "Released" di Katalog Modul untuk merilis ke semua pesantren.')
                    ->schema($this->moduleToggles('development'))
                    ->columns(2)
                    ->collapsed()
                    ->visible(fn (callable $get) => filled($get('tenant_id')) && $this->hasDevelopmentModules()),
            ]);
    }

    /**
     * Toggle modul untuk satu klasifikasi.
     *
     * @param  'core'|'optional'|'development'  $kind
     * @return array<int, Toggle>
     */
    protected function moduleToggles(string $kind): array
    {
        return ModuleRegistry::query()->orderBy('sort')->get()
            ->filter(fn (ModuleRegistry $m): bool => match ($kind) {
                'core' => (bool) $m->is_core,
                'development' => ! $m->is_core && $m->status === 'development',
                default => ! $m->is_core && $m->status !== 'development', // optional (released)
            })
            ->map(function (ModuleRegistry $m): Toggle {
                $toggle = Toggle::make("modules.{$m->key}")->label($m->label)->inline(false);

                if ($m->is_core) {
                    return $toggle->disabled()->helperText('🔒 Inti — selalu aktif');
                }
                if ($m->status === 'development') {
                    return $toggle->disabled()->helperText('🟠 Pengembangan — hanya demo');
                }

                return $toggle->helperText('🟢 Bisa diaktifkan/nonaktif per pesantren');
            })
            ->values()
            ->all();
    }

    protected function hasDevelopmentModules(): bool
    {
        return ModuleRegistry::query()
            ->where('is_core', false)
            ->where('status', 'development')
            ->exists();
    }

    /** Tombol bantu: aktif/nonaktif SEMUA modul opsional sekaligus (mempercepat onboarding tenant baru). */
    protected function getHeaderActions(): array
    {
        $adaTenant = fn (): bool => filled($this->data['tenant_id'] ?? null);

        return [
            Action::make('aktifkan_semua')
                ->label('Aktifkan semua opsional')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($adaTenant)
                ->requiresConfirmation()
                ->modalDescription('Mencentang semua modul opsional. Perubahan baru tersimpan setelah Anda menekan "Simpan Aktivasi".')
                ->action(fn () => $this->setSemuaOpsional(true)),
            Action::make('nonaktifkan_semua')
                ->label('Nonaktifkan semua opsional')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($adaTenant)
                ->requiresConfirmation()
                ->modalDescription('Melepas centang semua modul opsional. Perubahan baru tersimpan setelah Anda menekan "Simpan Aktivasi".')
                ->action(fn () => $this->setSemuaOpsional(false)),
        ];
    }

    protected function setSemuaOpsional(bool $aktif): void
    {
        $keys = ModuleRegistry::query()
            ->where('is_core', false)
            ->where('status', '!=', 'development')
            ->pluck('key');

        foreach ($keys as $key) {
            $this->data['modules'][$key] = $aktif;
        }

        Notification::make()
            ->title($aktif ? 'Semua modul opsional dicentang' : 'Semua modul opsional dilepas')
            ->body('Tekan "Simpan Aktivasi" untuk menerapkan ke pesantren ini.')
            ->success()
            ->send();
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
