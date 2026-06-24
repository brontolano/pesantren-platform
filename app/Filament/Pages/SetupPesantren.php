<?php

namespace App\Filament\Pages;

use App\Models\ModuleRegistry;
use App\Models\Setting;
use App\Services\KebijakanService;
use App\Services\MediaRegistrar;
use App\Services\ModuleService;
use App\Services\TenantHealthCheck;
use App\Support\KebijakanRegistry;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KalenderService;
use Modules\Akademik\Services\KenaikanKelasService;
use Modules\Core\Support\IstilahRegistry;
use Modules\Kesantrian\Services\DisiplinService;
use Modules\Ledger\Support\FiturKeuangan;

/**
 * Wizard Setup Pesantren TERPANDU (panel TENANT) — pintu UTAMA setup awal.
 *
 * Memandu fondasi pesantren langkah-demi-langkah sampai siap diisi data, ramah
 * untuk staf awam (mode Awam: bahasa 1 kalimat, default aman, langkah opsional
 * boleh dilewati). Semua simpan MEMAKAI ULANG mesin yang ada (Setting,
 * KebijakanService, model Lembaga/PeriodeAkademik, FiturKeuangan) — wizard hanya
 * lapisan pemandu, bukan sumber kebenaran baru.
 *
 * Idempoten & resume: mount() mengisi ulang dari Setting/model; membuka ulang
 * tak menimpa data baik. Progres dilacak di Setting 'setup.langkah_selesai';
 * 'setup.selesai' ditandai saat fondasi WAJIB lengkap (lihat wajibLengkap()).
 *
 * Lihat juga: SetupUnit (Pusat Setup = lanjutan isi data per unit) &
 * OnboardingChecklist (pengingat di Dasbor) — ketiganya berbagi indikator
 * progres lewat SetupPesantren::progress().
 */
class SetupPesantren extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Setup Pesantren';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Sistem';

    protected static ?int $navigationSort = -10;

    protected static ?string $title = 'Setup Pesantren';

    protected string $view = 'filament.pages.setup-pesantren';

    public ?array $data = [];

    /** Mode Awam (true) vs Lanjutan (false) — ikut UiMode global. */
    public bool $awam = true;

    /** Langkah yang dilacak untuk persen progres (lihat progress()). */
    public const LANGKAH_TRACKED = ['identitas', 'lembaga', 'periode', 'kebijakan', 'keuangan'];

    /** @var array<int,array{key:string,label:string,ok:bool,detail:string}>|null */
    private ?array $cacheCek = null;

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

    /** Badge "Mulai di sini" selama fondasi belum ditandai selesai. */
    public static function getNavigationBadge(): ?string
    {
        try {
            return Setting::getTenantValue('setup.selesai') === null ? 'Mulai di sini' : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function mount(): void
    {
        $this->awam = UiMode::simple();

        $svc = app(KebijakanService::class);
        $lembaga = Lembaga::query()->first();
        $periode = PeriodeAkademik::query()->where('is_active', true)->first();
        $istilah = is_array($v = Setting::getTenantValue(IstilahRegistry::SETTING_KEY)) ? $v : [];

        $this->form->fill([
            'nama' => Setting::getTenantStringValue('branding_name'),
            'tagline' => Setting::getTenantStringValue('branding_tagline'),
            'preset' => null, // jangan auto-terapkan ulang
            'labels' => [
                'santri' => $istilah['santri'] ?? null,
                'ustadz' => $istilah['ustadz'] ?? null,
            ],
            'lembaga_nama' => $lembaga?->nama,
            'lembaga_jenis' => $lembaga?->jenis ?? 'pondok',
            'periode_nama' => $periode?->nama,
            'tahun_akademik' => $periode?->tahun_akademik,
            'semester' => $periode?->semester ?? 'ganjil',
            'tanggal_mulai' => $periode?->tanggal_mulai,
            'tanggal_akhir' => $periode?->tanggal_akhir,
            'k_hari_libur' => $svc->nilai(KalenderService::SETTING_HARI_LIBUR_MINGGUAN),
            'k_penempatan' => $svc->nilai(KenaikanKelasService::SETTING_MODE_PENEMPATAN),
            'k_notif_wali' => $svc->nilai(DisiplinService::SETTING_NOTIF_WALI),
            'keuangan_jenis' => FiturKeuangan::jenisLembaga(),
            'keuangan_kehalalan' => FiturKeuangan::pemisahanKehalalanAktif(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $presetOptions = collect(KebijakanRegistry::presets())
            ->mapWithKeys(fn (array $p, string $k): array => [$k => $p['label'].' — '.$p['deskripsi']])
            ->all();
        $entries = KebijakanRegistry::entries();

        // statePath WAJIB di schema (bukan di Wizard) — kalau di Wizard, state
        // ter-nest lagi → getState() tak punya kunci datar → persist() error.
        return $schema->statePath('data')->components([
            Wizard::make([
                // 1) Selamat datang + arketipe ----------------------------------
                Step::make('Mulai')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Placeholder::make('selamat')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(
                                $this->awam
                                    ? '<p class="text-sm">Selamat datang! Ikuti langkah-langkah berikut untuk menyiapkan pondok Anda. Tiap langkah singkat, dan yang opsional boleh dilewati dulu — bisa diisi kapan saja.</p>'
                                    : '<p class="text-sm">Setup fondasi tenant. Mode Lanjutan: semua field tampil.</p>'
                            )),
                        Select::make('preset')
                            ->label('Jenis pesantren (arketipe)')
                            ->options($presetOptions)
                            ->native(false)
                            ->placeholder('Lewati (pakai default sistem)')
                            ->helperText(fn (): string => $this->awam
                                ? 'Pilih yang paling mirip — kami isikan pengaturan awal yang wajar. Bisa diubah kapan saja.'
                                : 'Menerapkan preset KebijakanService (mengisi banyak default sekaligus).'),
                    ]),

                // 2) Identitas & branding (+ istilah) ---------------------------
                Step::make('Identitas')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Pesantren')
                            ->required()
                            ->maxLength(255)
                            ->helperText(fn (): string => $this->awam ? 'Tulis nama pondok seperti di surat resmi.' : 'branding_name'),
                        TextInput::make('tagline')
                            ->label('Tagline / slogan (opsional)')
                            ->maxLength(255),
                        FileUpload::make('logo')
                            ->label('Logo (opsional)')
                            ->image()
                            ->disk(\App\Services\StorageQuotaService::diskName())
                            ->directory('branding')
                            ->maxSize(2048)
                            ->helperText('Maks 2MB. Boleh dilewati, unggah nanti.'),
                        TextInput::make('labels.santri')
                            ->label('Sebutan untuk "Santri"')
                            ->placeholder(IstilahRegistry::DEFAULTS['santri'])
                            ->visible(fn (): bool => ! $this->awam),
                        TextInput::make('labels.ustadz')
                            ->label('Sebutan untuk "Ustadz"')
                            ->placeholder(IstilahRegistry::DEFAULTS['ustadz'])
                            ->visible(fn (): bool => ! $this->awam),
                    ]),

                // 3) Modul aktif (READ-ONLY) ------------------------------------
                Step::make('Modul Aktif')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        Placeholder::make('modul_info')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(
                                view('filament.pages.partials.setup-modul-aktif', ['modul' => $this->daftarModul()])->render()
                            )),
                    ]),

                // 4) Lembaga ----------------------------------------------------
                Step::make('Lembaga')
                    ->icon('heroicon-o-building-library')
                    ->visible(fn (): bool => $this->akademikAktif())
                    ->schema([
                        TextInput::make('lembaga_nama')
                            ->label('Nama lembaga utama')
                            ->placeholder('mis. Pondok Pesantren / Madrasah Aliyah')
                            ->required(fn (): bool => $this->akademikAktif())
                            ->maxLength(255)
                            ->helperText(fn (): string => $this->awam ? 'Minimal satu lembaga. Pilih "Pondok" bila salaf tanpa madrasah formal.' : 'Lembaga::updateOrCreate by nama'),
                        Select::make('lembaga_jenis')
                            ->label('Jenis lembaga')
                            ->options([
                                'pondok' => 'Pondok Pesantren',
                                'formal' => 'Sekolah/Madrasah Formal',
                                'diniyah' => 'Madrasah Diniyah',
                                'tahfidz' => 'Tahfidz',
                                'mahad_aly' => "Ma'had Aly",
                                'unit_usaha' => 'Unit Usaha',
                            ])
                            ->default('pondok')
                            ->native(false)
                            ->required(fn (): bool => $this->akademikAktif()),
                    ]),

                // 5) Periode akademik -------------------------------------------
                Step::make('Periode Akademik')
                    ->icon('heroicon-o-calendar-days')
                    ->visible(fn (): bool => $this->akademikAktif())
                    ->schema([
                        TextInput::make('periode_nama')
                            ->label('Nama tahun ajaran')
                            ->placeholder('2026/2027 Ganjil')
                            ->required(fn (): bool => $this->akademikAktif())
                            ->maxLength(100),
                        TextInput::make('tahun_akademik')
                            ->label('Tahun akademik')
                            ->placeholder('2026/2027')
                            ->required(fn (): bool => $this->akademikAktif())
                            ->maxLength(20),
                        Select::make('semester')
                            ->label('Semester')
                            ->options(['ganjil' => 'Ganjil', 'genap' => 'Genap'])
                            ->default('ganjil')
                            ->native(false)
                            ->required(fn (): bool => $this->akademikAktif()),
                        DatePicker::make('tanggal_mulai')->label('Mulai')->required(fn (): bool => $this->akademikAktif()),
                        DatePicker::make('tanggal_akhir')->label('Selesai')->required(fn (): bool => $this->akademikAktif())->after('tanggal_mulai'),
                    ]),

                // 6) Kurikulum & program studi (opsional, link-out) -------------
                Step::make('Kurikulum')
                    ->icon('heroicon-o-book-open')
                    ->visible(fn (): bool => $this->akademikAktif())
                    ->schema([
                        Placeholder::make('kurikulum_info')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p class="text-sm">Kurikulum & program studi bisa diisi belakangan. Lewati dulu bila belum siap — buka menu <strong>Akademik → Kurikulum</strong> kapan saja.</p>'
                            )),
                    ]),

                // 7) Tinjau kebijakan -------------------------------------------
                Step::make('Kebijakan')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Placeholder::make('kebijakan_info')
                            ->hiddenLabel()
                            ->content(new HtmlString('<p class="text-sm">Beberapa kebijakan utama (sudah terisi dari arketipe). Cek sebentar; selengkapnya di menu <strong>Pengaturan → Kebijakan Pesantren</strong>.</p>')),
                        CheckboxList::make('k_hari_libur')
                            ->label($entries[KalenderService::SETTING_HARI_LIBUR_MINGGUAN]['label'] ?? 'Hari libur mingguan')
                            ->options([
                                'minggu' => 'Ahad (Minggu)', 'senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu',
                                'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu',
                            ])
                            ->columns(4)
                            ->helperText($entries[KalenderService::SETTING_HARI_LIBUR_MINGGUAN]['deskripsi'] ?? null),
                        Select::make('k_penempatan')
                            ->label($entries[KenaikanKelasService::SETTING_MODE_PENEMPATAN]['label'] ?? 'Mode penempatan kelas')
                            ->options($entries[KenaikanKelasService::SETTING_MODE_PENEMPATAN]['options'] ?? [])
                            ->native(false)
                            ->helperText($entries[KenaikanKelasService::SETTING_MODE_PENEMPATAN]['deskripsi'] ?? null),
                        Select::make('k_notif_wali')
                            ->label($entries[DisiplinService::SETTING_NOTIF_WALI]['label'] ?? 'Notifikasi wali soal pelanggaran')
                            ->options($entries[DisiplinService::SETTING_NOTIF_WALI]['options'] ?? [])
                            ->native(false)
                            ->helperText($entries[DisiplinService::SETTING_NOTIF_WALI]['deskripsi'] ?? null),
                    ]),

                // 8) Keuangan dasar (opsional) ----------------------------------
                Step::make('Keuangan')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Placeholder::make('keuangan_info')
                            ->hiddenLabel()
                            ->content(new HtmlString('<p class="text-sm">Pengaturan dasar keuangan. Bila belum mengurus dana BOS/BOP, lewati saja — bisa diatur nanti.</p>')),
                        Placeholder::make('skema_biaya_nudge')
                            ->hiddenLabel()
                            ->visible(fn (): bool => $this->keuanganAktif())
                            ->content(fn (): HtmlString => new HtmlString($this->skemaBiayaNudgeHtml())),
                        Select::make('keuangan_jenis')
                            ->label('Jenis lembaga (untuk dana negara)')
                            ->options(['pesantren' => 'Pesantren (BOP)', 'madrasah' => 'Madrasah (BOS)'])
                            ->default('pesantren')
                            ->native(false)
                            ->helperText(fn (): string => $this->awam ? 'Menentukan template dana negara (BOS vs BOP).' : 'keuangan.jenis_lembaga'),
                        Toggle::make('keuangan_kehalalan')
                            ->label('Aktifkan pemisahan dana halal/syubhat (Baitul Maal)')
                            ->helperText('Default nonaktif. Aktifkan bila pondok memisahkan dana sesuai kehalalan.'),
                    ]),

                // 9) Ringkasan + cek kesehatan ----------------------------------
                Step::make('Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Placeholder::make('ringkasan')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(
                                view('filament.pages.partials.setup-ringkasan', [
                                    'cek' => $this->cekKesehatan(),
                                    'wajibLengkap' => $this->wajibLengkap(),
                                ])->render()
                            )),
                    ]),
            ])
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

        if (Setting::getTenantValue('setup.selesai') !== null) {
            Notification::make()
                ->title('Fondasi pesantren selesai 🎉')
                ->body('Sekarang pilih fitur yang Anda butuhkan di Pusat Modul — bisa nambah kapan saja.')
                ->success()->persistent()->send();
            // Fondasi → Pusat Modul (pilih fitur) → wizard per-fitur. Bukan langsung
            // ke Pusat Setup; fitur dipasang dulu baru data unit-nya dilengkapi.
            $this->redirect(PusatModul::canAccess() ? PusatModul::getUrl() : SetupUnit::getUrl());

            return;
        }

        Notification::make()
            ->title('Tersimpan — tinggal beberapa langkah wajib')
            ->body('Lengkapi nama pesantren'.($this->akademikAktif() ? ', lembaga, dan periode akademik' : '').' agar fondasi dianggap selesai.')
            ->warning()->persistent()->send();
    }

    /**
     * Tulis hasil wizard ke Setting/Service/model yang sudah ada (reuse, idempoten).
     * Dipisah dari simpan() agar bisa diuji tanpa lifecycle Livewire.
     *
     * @param  array<string, mixed>  $data
     */
    public function persist(array $data): void
    {
        $selesai = [];

        // 1) Arketipe (opsional). Selain menerapkan preset kebijakan, SIMPAN
        //    pilihannya (setup.preset) → dipakai mesin rekomendasi Pusat Modul
        //    untuk menyarankan fitur yang cocok dengan profil pesantren.
        if (! empty($data['preset'])) {
            app(KebijakanService::class)->terapkanPreset($data['preset'], auth()->user());
            Setting::updateOrCreate(['key' => 'setup.preset'], ['value' => ['text' => $data['preset']]]);
            $selesai[] = 'arketipe';
        }

        // 2) Identitas & branding + istilah.
        if (! empty($data['nama'])) {
            Setting::updateOrCreate(['key' => 'branding_name'], ['value' => ['text' => $data['nama']]]);
            if (array_key_exists('tagline', $data)) {
                Setting::updateOrCreate(['key' => 'branding_tagline'], ['value' => ['text' => $data['tagline']]]);
            }
            if (! empty($data['logo'])) {
                app(MediaRegistrar::class)->register($data['logo'], 'branding');
                Setting::updateOrCreate(['key' => 'branding_logo'], ['value' => ['logo_path' => $data['logo']]]);
            }
            $this->simpanIstilah($data['labels'] ?? []);
            $selesai[] = 'identitas';
        }

        // 4) Lembaga.
        if (! empty($data['lembaga_nama'])) {
            Lembaga::updateOrCreate(
                ['nama' => $data['lembaga_nama']],
                ['jenis' => $data['lembaga_jenis'] ?? 'pondok', 'aktif' => true],
            );
            $selesai[] = 'lembaga';
        }

        // 5) Periode akademik aktif (idempoten by tahun+semester). Tanggal WAJIB
        //    diisi (kolom NOT NULL) — guard juga di tanggal agar pemanggil langsung
        //    persist() tanpa tanggal tak memicu QueryException.
        if (! empty($data['tahun_akademik']) && ! empty($data['semester'])
            && ! empty($data['tanggal_mulai']) && ! empty($data['tanggal_akhir'])) {
            PeriodeAkademik::query()->where('is_active', true)->update(['is_active' => false]);
            PeriodeAkademik::updateOrCreate(
                ['tahun_akademik' => $data['tahun_akademik'], 'semester' => $data['semester']],
                [
                    'nama' => $data['periode_nama'] ?? $data['tahun_akademik'].' '.$data['semester'],
                    'tanggal_mulai' => $data['tanggal_mulai'],
                    'tanggal_akhir' => $data['tanggal_akhir'],
                    'is_active' => true,
                ],
            );
            $selesai[] = 'periode';
        }

        // 7) Tinjau kebijakan (3 kunci) via KebijakanService (validasi + audit + idempoten).
        $svc = app(KebijakanService::class);
        $user = auth()->user();
        $kebijakanDitulis = false;
        if (array_key_exists('k_hari_libur', $data) && is_array($data['k_hari_libur'])) {
            $svc->set(KalenderService::SETTING_HARI_LIBUR_MINGGUAN, array_values($data['k_hari_libur']), $user);
            $kebijakanDitulis = true;
        }
        if (! empty($data['k_penempatan'])) {
            $svc->set(KenaikanKelasService::SETTING_MODE_PENEMPATAN, $data['k_penempatan'], $user);
            $kebijakanDitulis = true;
        }
        if (! empty($data['k_notif_wali'])) {
            $svc->set(DisiplinService::SETTING_NOTIF_WALI, $data['k_notif_wali'], $user);
            $kebijakanDitulis = true;
        }
        if ($kebijakanDitulis) {
            $selesai[] = 'kebijakan';
        }

        // 8) Keuangan dasar — bentuk nilai dibaca FiturKeuangan (BUKAN via KebijakanService).
        if (! empty($data['keuangan_jenis'])) {
            Setting::updateOrCreate(['key' => FiturKeuangan::KEY_JENIS_LEMBAGA], ['value' => ['text' => $data['keuangan_jenis']]]);
            Setting::updateOrCreate(['key' => FiturKeuangan::KEY_PEMISAHAN_KEHALALAN], ['value' => ['enabled' => (bool) ($data['keuangan_kehalalan'] ?? false)]]);
            $selesai[] = 'keuangan';
        }

        $this->catatProgres($selesai);

        // Tandai fondasi selesai HANYA bila langkah wajib lengkap.
        if ($this->wajibLengkap()) {
            Setting::updateOrCreate(['key' => 'setup.selesai'], ['value' => ['at' => now()->toIso8601String()]]);
        }
    }

    /** Gabungkan istilah baru ke map override yang ada, lalu flush cache. */
    private function simpanIstilah(array $labels): void
    {
        $labels = array_filter($labels, fn ($v): bool => is_string($v) && trim($v) !== '');
        if ($labels === []) {
            return;
        }
        $map = is_array($v = Setting::getTenantValue(IstilahRegistry::SETTING_KEY)) ? $v : [];
        foreach ($labels as $k => $val) {
            if (array_key_exists($k, IstilahRegistry::DEFAULTS)) {
                $map[$k] = trim($val);
            }
        }
        Setting::updateOrCreate(['key' => IstilahRegistry::SETTING_KEY], ['value' => $map]);
        IstilahRegistry::flushCache();
    }

    /** Union langkah selesai ke Setting (idempoten). */
    private function catatProgres(array $langkah): void
    {
        if ($langkah === []) {
            return;
        }
        $lama = is_array($v = Setting::getTenantValue('setup.langkah_selesai')) ? $v : [];
        $baru = array_values(array_unique([...$lama, ...$langkah]));
        Setting::updateOrCreate(['key' => 'setup.langkah_selesai'], ['value' => $baru]);
    }

    /** Apakah modul akademik tampil untuk tenant ini (gerbang langkah Lembaga/Periode). */
    protected function akademikAktif(): bool
    {
        try {
            return app(ModuleService::class)->isVisibleForTenant('akademik', tenancy()->initialized ? tenant() : null);
        } catch (\Throwable) {
            return true;
        }
    }

    /** Apakah modul keuangan tampil untuk tenant ini (gerbang nudge Skema Biaya). */
    protected function keuanganAktif(): bool
    {
        try {
            return app(ModuleService::class)->isVisibleForTenant('keuangan', tenancy()->initialized ? tenant() : null);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Apakah Skema Biaya (tarif baku) sudah diisi tenant ini?
     *
     * Penting: bila kosong, importer Syahriyyah memakai nominal fallback bawaan
     * (700rb) saat membuat Tagihan baru. Mengisi Skema Biaya membuat tarif sesuai
     * pondok. Nudge ini HANYA membaca data — tak mengubah fallback/logika import.
     */
    protected function skemaBiayaTerisi(): bool
    {
        try {
            return \Modules\Keuangan\Models\SkemaBiaya::query()->where('aktif', true)->exists();
        } catch (\Throwable) {
            // Modul/tabel belum tersedia → anggap belum terisi (tampilkan ajakan).
            return false;
        }
    }

    /** Tautan ke menu pengisian Skema Biaya, bila resource dapat diakses. */
    protected function skemaBiayaUrl(): ?string
    {
        try {
            $resource = \Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource::class;

            return $resource::canAccess() ? $resource::getUrl('index') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** HTML indikator ramah: status Skema Biaya + arahan ke menu pengisiannya. */
    protected function skemaBiayaNudgeHtml(): string
    {
        if ($this->skemaBiayaTerisi()) {
            return '<div class="rounded-lg bg-green-50 px-4 py-3 ring-1 ring-green-200 dark:bg-green-500/10 dark:ring-green-500/20">'
                .'<p class="text-sm font-semibold text-green-800 dark:text-green-300">Skema Biaya sudah diisi ✓</p>'
                .'<p class="text-sm text-green-700 dark:text-green-400">Tarif baku tagihan akan memakai skema pondok Anda.</p>'
                .'</div>';
        }

        $url = $this->skemaBiayaUrl();
        $ajakan = $url !== null
            ? ' Buka menu <a href="'.e($url).'" class="font-semibold underline">Keuangan → Skema Biaya</a> untuk mengaturnya.'
            : ' Atur lewat menu <strong>Keuangan → Skema Biaya</strong> (mode Lanjutan).';

        return '<div class="rounded-lg bg-amber-50 px-4 py-3 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/20">'
            .'<p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Skema Biaya belum diisi</p>'
            .'<p class="text-sm text-amber-700 dark:text-amber-400">Tanpa Skema Biaya, tarif baku tagihan (mis. saat impor pembayaran syahriyyah) memakai nominal bawaan <strong>Rp 700.000</strong>. Isi Skema Biaya agar tarif sesuai pondok Anda.'
            .$ajakan.'</p>'
            .'</div>';
    }

    /** Fondasi WAJIB: nama terisi + (akademik mati ATAU ada lembaga & periode aktif). */
    protected function wajibLengkap(): bool
    {
        if (Setting::getTenantStringValue('branding_name') === '') {
            return false;
        }
        if (! $this->akademikAktif()) {
            return true;
        }

        return Lembaga::query()->exists()
            && PeriodeAkademik::query()->where('is_active', true)->exists();
    }

    /** Daftar modul + status aktif (read-only) untuk langkah info. */
    public function daftarModul(): array
    {
        try {
            $svc = app(ModuleService::class);
            $tenant = tenancy()->initialized ? tenant() : null;

            return ModuleRegistry::query()->orderBy('sort')->get()->map(fn (ModuleRegistry $m): array => [
                'label' => $m->label,
                'aktif' => $svc->isVisibleForTenant($m->key, $tenant),
                'inti' => (bool) $m->is_core,
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Cek kesehatan ringan (tanpa render-check yang berat), di-memo per request. */
    private function cekKesehatan(): array
    {
        try {
            return $this->cacheCek ??= app(TenantHealthCheck::class)->run(tenant(), withRender: false);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Indikator progres bersama (dipakai SetupUnit & OnboardingChecklist).
     *
     * @return array{selesai:bool, langkah:int, total:int, persen:int}
     */
    public static function progress(): array
    {
        try {
            $done = is_array($v = Setting::getTenantValue('setup.langkah_selesai')) ? $v : [];
            $langkah = count(array_intersect(self::LANGKAH_TRACKED, $done));
            $total = count(self::LANGKAH_TRACKED);

            return [
                'selesai' => Setting::getTenantValue('setup.selesai') !== null,
                'langkah' => $langkah,
                'total' => $total,
                'persen' => $total > 0 ? (int) round($langkah / $total * 100) : 0,
            ];
        } catch (\Throwable) {
            return ['selesai' => false, 'langkah' => 0, 'total' => count(self::LANGKAH_TRACKED), 'persen' => 0];
        }
    }
}
