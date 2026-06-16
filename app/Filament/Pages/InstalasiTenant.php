<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Tenant;
use App\Services\TenantHealthCheck;
use App\Services\TenantProvisioner;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Wizard Instalasi Tenant (CENTRAL only) — "click to install".
 *
 * Membungkus mesin provisioning yang sudah ada (Tenant::create →
 * CreateDatabase+MigrateDatabase → TenantProvisioner) dengan:
 *  1. Validasi proaktif sebelum instal (slug unik, format domain)
 *  2. Profil awal opsional (warna, koordinat) menimpa default seeder
 *  3. Health check otomatis pasca-instal (TenantHealthCheck) — tenant
 *     "setengah jadi" ketahuan SEBELUM diserahkan ke pemilik pesantren
 *  4. Kartu kredensial yang bisa disalin + tombol Perbaiki Otomatis
 *     (tenant reprovision idempotent) bila ada cek gagal.
 */
class InstalasiTenant extends Page implements HasForms
{
    use InteractsWithForms;

    public const SUBDOMAIN_SUFFIX = '.pesantren.online';

    /** Slug yang tak boleh dipakai tenant (bentrok infrastruktur). */
    private const RESERVED_SLUGS = ['www', 'platform', 'admin', 'api', 'mail', 'sistem', 'app', 'dokumentasi'];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Instalasi Tenant';

    protected static string|\UnitEnum|null $navigationGroup = 'Pesantren';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Instalasi Tenant Baru';

    protected string $view = 'filament.pages.instalasi-tenant';

    public ?array $data = [];

    /** Hasil instalasi — diisi setelah install() agar view menampilkan ringkasan. */
    public ?array $result = null;

    public bool $installing = false;

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
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Identitas')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pesantren')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Tenant ID (slug)')
                            ->helperText('Huruf kecil/angka/strip. Menjadi subdomain: {slug}'.self::SUBDOMAIN_SUFFIX)
                            ->required()
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->maxLength(40)
                            ->live(onBlur: true)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if (in_array($value, self::RESERVED_SLUGS, true)) {
                                    $fail("Slug '{$value}' dicadangkan untuk platform.");
                                }
                                if (Tenant::query()->whereKey($value)->exists()) {
                                    $fail("Tenant '{$value}' sudah ada.");
                                }
                            }),
                        TextInput::make('admin_email')
                            ->label('Email Admin Pesantren')
                            ->helperText('Akun super_admin pertama dibuat dengan email ini.')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ]),
                Step::make('Domain')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Placeholder::make('subdomain_info')
                            ->label('Subdomain (otomatis)')
                            ->content(fn (callable $get): HtmlString => new HtmlString(
                                '<code>'.e(($get('slug') ?: '{slug}').self::SUBDOMAIN_SUFFIX).'</code>'
                                .' — DNS wildcard & SSL on-demand sudah aktif, tanpa setup tambahan.'
                            )),
                        TextInput::make('website_domain')
                            ->label('Domain Website Kustom (opsional)')
                            ->helperText('Misal: asy-syifaa.sch.id — pemilik domain harus mengarahkan DNS ke server. Kosongkan bila cukup subdomain.')
                            ->nullable()
                            ->regex('/^(?!https?:)[a-z0-9.-]+\.[a-z]{2,}$/i')
                            ->maxLength(255),
                        TextInput::make('sistem_domain')
                            ->label('Domain Panel Admin Kustom (opsional)')
                            ->helperText('Misal: sistem.asy-syifaa.sch.id. Kosongkan bila pakai subdomain.')
                            ->nullable()
                            ->regex('/^(?!https?:)[a-z0-9.-]+\.[a-z]{2,}$/i')
                            ->maxLength(255),
                    ]),
                Step::make('Profil Awal')
                    ->icon('heroicon-o-paint-brush')
                    ->description('Opsional — bisa dilewati, admin pesantren dapat mengubahnya nanti')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('Warna Utama (opsional)'),
                        ColorPicker::make('secondary_color')
                            ->label('Warna Sekunder (opsional)'),
                        TextInput::make('latitude')
                            ->label('Latitude (opsional)')
                            ->helperText('Koordinat pesantren untuk jadwal sholat. Contoh: -6.85')
                            ->nullable()
                            ->numeric(),
                        TextInput::make('longitude')
                            ->label('Longitude (opsional)')
                            ->helperText('Contoh: 107.49')
                            ->nullable()
                            ->numeric(),
                    ]),
                Step::make('Konfirmasi & Instal')
                    ->icon('heroicon-o-rocket-launch')
                    ->schema([
                        Placeholder::make('ringkasan')
                            ->label('Ringkasan')
                            ->content(function (callable $get): HtmlString {
                                $rows = [
                                    ['Nama', $get('name')],
                                    ['Tenant ID', $get('slug')],
                                    ['Subdomain', ($get('slug') ?: '—').self::SUBDOMAIN_SUFFIX],
                                    ['Website kustom', $get('website_domain') ?: '— (pakai subdomain)'],
                                    ['Panel admin kustom', $get('sistem_domain') ?: '— (pakai subdomain)'],
                                    ['Email admin', $get('admin_email')],
                                ];
                                $html = '<table class="text-sm">';
                                foreach ($rows as [$k, $v]) {
                                    $html .= '<tr><td class="pr-4 py-1 font-medium">'.e($k).'</td><td>'.e((string) $v).'</td></tr>';
                                }

                                return new HtmlString($html.'</table>');
                            }),
                        Placeholder::make('proses_info')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                'Saat tombol <b>Instal Sekarang</b> ditekan: database fisik dibuat → seluruh migrasi modul'
                                .' → seeding (role, bagan akun, kalender, branding) → user admin → <b>health check otomatis</b>.'
                                .' Proses ±15–60 detik, jangan tutup halaman.'
                            )),
                    ]),
            ])->statePath('data')
                ->submitAction(new HtmlString(
                    '<button type="submit" class="fi-btn fi-btn-size-md fi-color-primary rounded-lg px-4 py-2 bg-primary-600 text-white font-semibold text-sm" wire:loading.attr="disabled">'
                    .'<span wire:loading.remove wire:target="install">🚀 Instal Sekarang</span>'
                    .'<span wire:loading wire:target="install">Menginstal… (±15–60 detik)</span>'
                    .'</button>'
                )),
        ]);
    }

    public function install(): void
    {
        $data = $this->form->getState();
        $slug = strtolower(trim($data['slug']));

        // Validasi ulang sisi server (race: tenant dibuat di tab lain).
        if (Tenant::query()->whereKey($slug)->exists() || in_array($slug, self::RESERVED_SLUGS, true)) {
            Notification::make()->title("Tenant ID '{$slug}' tidak tersedia.")->danger()->send();

            return;
        }

        try {
            // create() memicu TenantCreated → CreateDatabase + MigrateDatabase (sync).
            $tenant = Tenant::create([
                'id' => $slug,
                'name' => trim($data['name']),
                'is_active' => true,
            ]);

            // Domain: subdomain FQDN selalu + kustom bila diisi (pola CreateTenant page).
            $subdomain = $slug.self::SUBDOMAIN_SUFFIX;
            $website = strtolower(trim((string) ($data['website_domain'] ?? '')));
            $sistem = strtolower(trim((string) ($data['sistem_domain'] ?? '')));

            $tenant->domains()->updateOrCreate(
                ['domain' => $subdomain],
                ['type' => 'subdomain', 'is_primary' => $website === ''],
            );
            if ($website !== '') {
                $tenant->domains()->updateOrCreate(['domain' => $website], ['type' => 'website', 'is_primary' => true]);
            }
            if ($sistem !== '') {
                $tenant->domains()->updateOrCreate(['domain' => $sistem], ['type' => 'sistem', 'is_primary' => false]);
            }

            // Provisioning lengkap (idempotent, DRY dengan tenant:create).
            $creds = app(TenantProvisioner::class)->provision($tenant, trim($data['admin_email']));

            // Profil awal opsional — menimpa default seeder HANYA bila diisi.
            $this->applyProfilAwal($tenant, $data);

            // Health check pasca-instal.
            $checks = app(TenantHealthCheck::class)->run($tenant);

            $this->result = [
                'tenant_id' => $slug,
                'tenant_name' => $tenant->name,
                'admin_url' => 'https://'.($sistem !== '' ? $sistem : $subdomain).'/admin',
                'email' => $creds['email'],
                'password' => $creds['password'], // null bila user sudah ada
                'checks' => $checks,
                'all_ok' => collect($checks)->every(fn (array $c) => $c['ok']),
            ];

            Notification::make()
                ->title($this->result['all_ok'] ? 'Instalasi selesai — semua cek hijau ✅' : 'Instalasi selesai dengan peringatan ⚠️')
                ->{$this->result['all_ok'] ? 'success' : 'warning'}()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Instalasi gagal')
                ->body($e->getMessage().' — jalankan ulang wizard dengan slug yang sama untuk melanjutkan (provisioning idempotent), atau cek log.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /** Tombol "Perbaiki Otomatis": reprovision idempotent + health check ulang. */
    public function repair(): void
    {
        if (! $this->result) {
            return;
        }

        $tenant = Tenant::find($this->result['tenant_id']);
        if (! $tenant) {
            return;
        }

        try {
            app(TenantProvisioner::class)->provision($tenant, $this->result['email']);
            $checks = app(TenantHealthCheck::class)->run($tenant);
            $this->result['checks'] = $checks;
            $this->result['all_ok'] = collect($checks)->every(fn (array $c) => $c['ok']);

            Notification::make()
                ->title($this->result['all_ok'] ? 'Perbaikan berhasil — semua cek hijau ✅' : 'Masih ada cek gagal — lihat detail & log server')
                ->{$this->result['all_ok'] ? 'success' : 'warning'}()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Perbaikan gagal')->body($e->getMessage())->danger()->send();
        }
    }

    /** Mulai instalasi baru (reset form + hasil). */
    public function resetWizard(): void
    {
        $this->result = null;
        $this->form->fill();
    }

    /** Timpa default branding bila operator mengisinya di wizard. */
    private function applyProfilAwal(Tenant $tenant, array $data): void
    {
        $overrides = array_filter([
            'branding_primary_color' => filled($data['primary_color'] ?? null) ? ['color' => $data['primary_color']] : null,
            'branding_secondary_color' => filled($data['secondary_color'] ?? null) ? ['color' => $data['secondary_color']] : null,
            'branding_latitude' => filled($data['latitude'] ?? null) ? ['text' => (string) $data['latitude']] : null,
            'branding_longitude' => filled($data['longitude'] ?? null) ? ['text' => (string) $data['longitude']] : null,
        ]);

        if ($overrides === []) {
            return;
        }

        $tenant->run(function () use ($overrides): void {
            foreach ($overrides as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });
    }
}
