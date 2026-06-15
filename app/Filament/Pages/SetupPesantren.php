<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\KebijakanService;
use App\Support\KebijakanRegistry;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Akademik\Models\PeriodeAkademik;

/**
 * Wizard Setup Pesantren (TENANT panel) — versi POKOK.
 *
 * Memandu konfigurasi fondasi minimum agar pesantren baru siap diisi data:
 *  1. Identitas (nama pesantren → branding_name)
 *  2. Preset kebijakan (salafiyah-kecil | modern-multi-lembaga) via KebijakanService
 *  3. Periode akademik aktif (tahun ajaran berjalan)
 *
 * Reuse mesin yang ada (Setting, KebijakanService, model PeriodeAkademik) —
 * tidak menulis ulang logika. Setelah selesai, menandai Setting 'setup.selesai'
 * dan mengarahkan ke import Santri (fondasi data). Langkah lanjutan (modul,
 * branding penuh, EMIS, API/WA) menyusul di iterasi berikutnya.
 */
class SetupPesantren extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Setup Pesantren';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan & Mutu';

    protected static ?int $navigationSort = -10;

    protected static ?string $title = 'Setup Pesantren';

    protected string $view = 'filament.pages.setup-pesantren';

    public ?array $data = [];

    /** Tenant panel + user pengelola. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (auth()->user()?->canAny(['manage_santri', 'manage_akademik', 'manage_keuangan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'nama' => Setting::getTenantStringValue('branding_name'),
            'semester' => 'ganjil',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $presetOptions = collect(KebijakanRegistry::presets())
            ->mapWithKeys(fn (array $p, string $k): array => [$k => $p['label'].' — '.$p['deskripsi']])
            ->all();

        return $schema->components([
            Wizard::make([
                Step::make('Identitas')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Pesantren')
                            ->required()
                            ->maxLength(255),
                    ]),
                Step::make('Kebijakan')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Placeholder::make('info_preset')
                            ->label('Titik awal kebijakan')
                            ->content('Pilih preset yang paling mendekati pesantren Anda. Nilainya bisa disesuaikan kapan saja di halaman Kebijakan Pesantren.'),
                        Select::make('preset')
                            ->label('Preset kebijakan')
                            ->options($presetOptions)
                            ->native(false)
                            ->placeholder('Lewati (pakai default sistem)'),
                    ]),
                Step::make('Periode Akademik')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        TextInput::make('periode_nama')
                            ->label('Nama tahun ajaran')
                            ->placeholder('2026/2027 Ganjil')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('tahun_akademik')
                            ->label('Tahun akademik')
                            ->placeholder('2026/2027')
                            ->required()
                            ->maxLength(20),
                        Select::make('semester')
                            ->label('Semester')
                            ->options(['ganjil' => 'Ganjil', 'genap' => 'Genap'])
                            ->default('ganjil')
                            ->native(false)
                            ->required(),
                        DatePicker::make('tanggal_mulai')->label('Mulai')->required(),
                        DatePicker::make('tanggal_akhir')->label('Selesai')->required()->after('tanggal_mulai'),
                    ]),
            ])->statePath('data')
                ->submitAction(new HtmlString(
                    '<button type="submit" class="fi-btn fi-btn-size-md fi-color-primary rounded-lg px-4 py-2 bg-primary-600 text-white font-semibold text-sm" wire:loading.attr="disabled">'
                    .'<span wire:loading.remove wire:target="simpan">Selesai &amp; Simpan</span>'
                    .'<span wire:loading wire:target="simpan">Menyimpan…</span>'
                    .'</button>'
                )),
        ]);
    }

    public function simpan(): void
    {
        $this->persist($this->form->getState());

        Notification::make()
            ->title('Setup pokok selesai')
            ->body('Identitas, kebijakan, dan periode akademik tersimpan. Lanjut isi data lewat menu Import.')
            ->success()
            ->persistent()
            ->send();

        $this->redirect(route('filament.tenant.resources.santri.santris.index'));
    }

    /**
     * Tulis hasil wizard ke Setting/KebijakanService/PeriodeAkademik.
     * Dipisah dari simpan() agar logika inti bisa diuji tanpa lifecycle Livewire.
     *
     * @param  array<string, mixed>  $data
     */
    public function persist(array $data): void
    {
        // 1) Identitas → branding_name.
        Setting::updateOrCreate(['key' => 'branding_name'], ['value' => ['text' => $data['nama']]]);

        // 2) Preset kebijakan (opsional) — reuse KebijakanService.
        if (! empty($data['preset'])) {
            app(KebijakanService::class)->terapkanPreset($data['preset'], auth()->user());
        }

        // 3) Periode akademik aktif (idempoten by tahun+semester).
        PeriodeAkademik::query()
            ->where('is_active', true)
            ->update(['is_active' => false]);

        PeriodeAkademik::updateOrCreate(
            ['tahun_akademik' => $data['tahun_akademik'], 'semester' => $data['semester']],
            [
                'nama' => $data['periode_nama'],
                'tanggal_mulai' => $data['tanggal_mulai'],
                'tanggal_akhir' => $data['tanggal_akhir'],
                'is_active' => true,
            ],
        );

        // Tandai setup pokok selesai.
        Setting::updateOrCreate(['key' => 'setup.selesai'], ['value' => ['at' => now()->toIso8601String()]]);
    }
}
