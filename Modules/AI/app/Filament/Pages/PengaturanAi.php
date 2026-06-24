<?php

namespace Modules\AI\Filament\Pages;

use App\Models\User;
use App\Services\ModuleService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiTenantBudget;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Metering\AiBudgetProvisioner;
use Modules\AI\Services\Metering\BudgetGuard;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\RingkasanKebijakan;
use Modules\AI\Services\Settings\TenantSettingService;
use Throwable;

/**
 * T-A011 — Wizard "Wawancara Digital" 6 langkah (PRD §6.2–6.3).
 *
 * Admin pesantren mengisi kebijakan AI lewat bahasa manusia; tiap pindah
 * langkah draf disimpan otomatis (versi draf yang SAMA diperbarui, bukan
 * versi baru per langkah). Langkah akhir menampilkan Ringkasan Kebijakan
 * (template deterministik, bukan LLM) + tombol "Ajukan ke Pimpinan" yang
 * memicu alur persetujuan Kyai (TenantSettingService::ajukan).
 */
class PengaturanAi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim';

    protected static ?string $navigationLabel = 'Pengaturan Asisten AI';

    protected static ?string $title = 'Wawancara Digital — Pengaturan Asisten AI';

    protected static ?string $slug = 'pengaturan-ai';

    protected static ?int $navigationSort = 1;

    protected string $view = 'ai::filament.pages.pengaturan-ai';

    /** State form Wizard (statePath). */
    public ?array $data = [];

    /** Versi draf yang sedang dikerjakan (null = belum ada draf). */
    public ?int $drafVersi = null;

    /**
     * Payload dasar tempat nilai form ditimpakan. Menjaga field yang TIDAK
     * diedit lewat form (mis. matriks fitur_per_peran preset) agar tidak
     * hilang saat draf disimpan. whitelist_kitab kini DIKELOLA MANDIRI oleh
     * pesantren lewat form (gap-83) — perubahannya tergolong sensitif →
     * menunggu persetujuan Kyai/Mudir (config ai.settings.sensitive_fields).
     */
    public array $payloadDasar = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        if (! self::modulAiTampil()) {
            return false;
        }

        $user = Auth::user();

        return $user !== null
            && ($user->can('ai.setting.kelola') || $user->can('ai.setting.lihat'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Gerbang modul (Blueprint Bagian 8): modul `ai` bertipe development. */
    protected static function modulAiTampil(): bool
    {
        return app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);
    }

    public function mount(): void
    {
        $defaults = AiTenantSettingDefaults::konservatif();

        // Lanjutkan dari draf terakhir; bila tidak ada, mulai dari versi aktif.
        $draf = AiTenantSetting::query()
            ->where('status', AiTenantSetting::STATUS_DRAFT)
            ->orderByDesc('versi')
            ->first();

        $sumber = $draf?->payload
            ?? app(TenantSettingService::class)->aktif()?->payload
            ?? $defaults;

        $this->drafVersi = $draf?->versi;
        $this->payloadDasar = array_replace($defaults, $sumber);

        $this->form->fill($this->payloadDasar);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    $this->langkahIdentitasManhaj(),
                    $this->langkahBatasanSyariah(),
                    $this->langkahFiturPerPeran(),
                    $this->langkahDataPrivasi(),
                    $this->langkahKanalJamBahasa(),
                    $this->langkahAnggaran(),
                ])
                    ->columnSpanFull()
                    ->skippable(false)
                    ->submitAction(
                        $this->bolehKelola()
                            ? new HtmlString(Blade::render(<<<'BLADE'
                                <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                                    Ajukan ke Pimpinan
                                </x-filament::button>
                                BLADE))
                            : null
                    ),
            ]);
    }

    // ------------------------------------------------------------------
    // Langkah 1 — Identitas & Manhaj
    // ------------------------------------------------------------------

    protected function langkahIdentitasManhaj(): Step
    {
        return Step::make('Identitas & Manhaj')
            ->icon('heroicon-o-building-library')
            ->description('Haluan keilmuan dan gaya bertutur asisten')
            ->afterValidation(fn () => $this->simpanLangkah())
            ->schema([
                Radio::make('preset_manhaj')
                    ->label('Haluan (manhaj) pesantren')
                    ->helperText('Menentukan paket rujukan yang kelak dimuat setelah ditinjau Dewan Ustadz. Asisten tidak pernah mengarang isi keagamaan sendiri.')
                    ->options([
                        'aswaja_nu' => 'Ahlussunnah wal Jama\'ah (NU)',
                        'muhammadiyah' => 'Muhammadiyah',
                        'salafi' => 'Salafi',
                        'kustom' => 'Kustom (diatur sendiri bersama Dewan Ustadz)',
                    ])
                    ->descriptions([
                        'aswaja_nu' => 'Paket rujukan mengikuti tradisi pesantren NU.',
                        'muhammadiyah' => 'Paket rujukan mengikuti Majelis Tarjih.',
                        'salafi' => 'Paket rujukan mengikuti manhaj salaf.',
                        'kustom' => 'Daftar rujukan disusun khusus oleh pesantren Anda.',
                    ])
                    ->default('aswaja_nu')
                    ->required()
                    ->columnSpanFull(),
                Select::make('bahasa')
                    ->label('Bahasa utama')
                    ->options([
                        'id' => 'Bahasa Indonesia',
                        'id_jawa' => 'Indonesia dengan sentuhan Jawa',
                        'id_sunda' => 'Indonesia dengan sentuhan Sunda',
                    ])
                    ->required(),
                Select::make('gaya')
                    ->label('Gaya bertutur')
                    ->options([
                        'formal_santun' => 'Formal & santun',
                        'hangat' => 'Hangat & akrab (tetap santun)',
                    ])
                    ->required(),
            ])
            ->columns(2);
    }

    // ------------------------------------------------------------------
    // Langkah 2 — Batasan Syariah
    // ------------------------------------------------------------------

    protected function langkahBatasanSyariah(): Step
    {
        return Step::make('Batasan Syariah')
            ->icon('heroicon-o-shield-check')
            ->description('Sejauh mana asisten boleh menyentuh ranah agama')
            ->afterValidation(fn () => $this->simpanLangkah())
            ->schema([
                Radio::make('level_fatwa')
                    ->label('Batas jawaban keagamaan')
                    ->options([
                        'edukatif_only' => 'Hanya edukatif (paling ketat)',
                        'informatif' => 'Informatif dengan rujukan',
                        'khilafiyah' => 'Memaparkan ragam pendapat',
                    ])
                    ->descriptions([
                        'edukatif_only' => 'Asisten hanya menjawab materi pelajaran dan administrasi. Semua pertanyaan hukum agama dialihkan kepada Asatidz.',
                        'informatif' => 'Asisten boleh menyampaikan informasi dari daftar rujukan yang telah ditinjau Dewan Ustadz, selalu dengan sumber, tanpa menyimpulkan hukum.',
                        'khilafiyah' => 'Asisten boleh memaparkan beberapa pendapat dari rujukan yang diizinkan beserta sumbernya, tanpa menetapkan satu pendapat sebagai keputusan.',
                    ])
                    ->required()
                    ->columnSpanFull(),
                TagsInput::make('whitelist_kitab')
                    ->label('Daftar rujukan yang diizinkan (whitelist kitab)')
                    ->helperText(new HtmlString(
                        'Daftar kitab/rujukan yang BOLEH dirujuk asisten saat menjawab pertanyaan keagamaan. '
                        .'Disusun dan menjadi tanggung jawab pesantren (mis. atas arahan Dewan Asatidz) — '
                        .'asisten tetap tidak pernah mengarang isi keagamaan, hanya merujuk daftar ini. '
                        .'Kosongkan bila asisten cukup mempersilakan bertanya langsung kepada Asatidz. '
                        .'Unggah dokumen rujukannya di menu <strong>Sumber Pengetahuan</strong>. '
                        .'Perubahan daftar ini menunggu persetujuan Kyai/Mudir sebelum berlaku.'
                    ))
                    ->placeholder('Ketik nama kitab/rujukan lalu tekan Enter')
                    ->columnSpanFull(),
                TagsInput::make('blacklist_topik')
                    ->label('Topik yang dilarang dibahas')
                    ->helperText('Daftar baku per manhaj akan tersedia setelah ditinjau Dewan Ustadz. Pesantren tetap dapat menambahkan topik larangan sendiri di sini.')
                    ->placeholder('Ketik topik lalu tekan Enter')
                    ->columnSpanFull(),
                Section::make('Eskalasi "Tanya Ustadz"')
                    ->description('Ke mana pertanyaan di luar wewenang asisten diteruskan.')
                    ->schema([
                        Select::make('eskalasi_tujuan.penerima')
                            ->label('Penerima eskalasi (Asatidz / pengurus)')
                            ->multiple()
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->helperText('Pilih satu atau beberapa penerima yang menindaklanjuti pertanyaan.'),
                        Select::make('eskalasi_tujuan.kanal')
                            ->label('Kanal pemberitahuan')
                            ->options(['panel' => 'Panel admin', 'wa' => 'WhatsApp'])
                            ->required(),
                        TextInput::make('eskalasi_tujuan.sla_jam')
                            ->label('Target tindak lanjut (jam)')
                            ->numeric()->minValue(1)->maxValue(168)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    // ------------------------------------------------------------------
    // Langkah 3 — Fitur per Peran
    // ------------------------------------------------------------------

    protected function langkahFiturPerPeran(): Step
    {
        $labelPeran = [
            'asatidz' => 'Asatidz',
            'admin' => 'Staf Administrasi (TU)',
            'bendahara' => 'Bendahara',
            'wali' => 'Wali Santri',
            'santri' => 'Santri',
        ];

        $labelFitur = [
            'generator_soal' => 'Pembuat draf soal latihan',
            'generator_rpp' => 'Pembuat draf RPP / modul ajar',
            'chatbot' => 'Tanya-jawab (chatbot)',
            'dokumen_tu' => 'Draf dokumen administrasi',
            'ringkasan_keuangan' => 'Ringkasan laporan keuangan',
            'tutor' => 'Tutor belajar',
        ];

        $bagianPeran = [];
        foreach (AiTenantSettingDefaults::konservatif()['fitur_per_peran'] as $peran => $fiturs) {
            $toggles = [];
            foreach (array_keys($fiturs) as $fitur) {
                $toggle = Toggle::make("fitur_per_peran.{$peran}.{$fitur}")
                    ->label($labelFitur[$fitur] ?? $fitur)
                    ->inline(false);

                if ($peran === 'santri') {
                    $toggle->helperText('Butuh persetujuan wali santri (consent) + persetujuan Kyai. Direncanakan pada Fase C.');
                }

                $toggles[] = $toggle;
            }

            $section = Section::make($labelPeran[$peran] ?? ucfirst($peran))
                ->schema($toggles)
                ->columns(2)
                ->columnSpanFull();

            if ($peran === 'santri') {
                $section->description('Fitur untuk santri bawaan-nya NONAKTIF demi kehati-hatian.');
            }

            $bagianPeran[] = $section;
        }

        $bagianPeran[] = Radio::make('mode_akses_santri')
            ->label('Cara santri mengakses (bila kelak fitur santri diaktifkan)')
            ->options([
                'tanpa_akses' => 'Tanpa akses sama sekali',
                'lab_terjadwal' => 'Hanya dari lab komputer pada jadwal tertentu',
                'perangkat_terbatas' => 'Perangkat terdaftar dengan pendampingan',
            ])
            ->descriptions([
                'tanpa_akses' => 'Pilihan paling aman — santri tidak dapat membuka asisten.',
                'lab_terjadwal' => 'Akses hanya dari perangkat pesantren pada jam yang dijadwalkan.',
                'perangkat_terbatas' => 'Akses dari perangkat yang didaftarkan dan diawasi pengurus.',
            ])
            ->required()
            ->columnSpanFull();

        return Step::make('Fitur per Peran')
            ->icon('heroicon-o-user-group')
            ->description('Siapa boleh memakai fitur apa')
            ->afterValidation(fn () => $this->simpanLangkah())
            ->schema($bagianPeran);
    }

    // ------------------------------------------------------------------
    // Langkah 4 — Data & Privasi
    // ------------------------------------------------------------------

    protected function langkahDataPrivasi(): Step
    {
        $labelScope = [
            'tagihan' => 'Tagihan santri',
            'jadwal' => 'Jadwal kegiatan & pelajaran',
            'nilai' => 'Nilai akademik',
            'kehadiran' => 'Kehadiran santri',
            'kesehatan' => 'Data kesehatan (sangat sensitif)',
            'keuangan' => 'Keuangan pesantren',
        ];

        $toggles = [];
        foreach ($labelScope as $scope => $label) {
            $toggles[] = Toggle::make("scope_data.{$scope}")
                ->label($label)
                ->inline(false);
        }

        return Step::make('Data & Privasi')
            ->icon('heroicon-o-lock-closed')
            ->description('Data apa yang boleh dibaca asisten, dan berapa lama riwayat disimpan')
            ->afterValidation(fn () => $this->simpanLangkah())
            ->schema([
                Section::make('Data yang boleh dibaca asisten')
                    ->description('Asisten hanya membaca data yang dicentang — itupun terbatas pada penanya yang memang berwenang melihatnya.')
                    ->schema($toggles)
                    ->columns(3)
                    ->columnSpanFull(),
                TextInput::make('retensi_hari')
                    ->label('Riwayat percakapan disimpan (hari)')
                    ->numeric()->minValue(1)->maxValue(3650)
                    ->required(),
                TextInput::make('retensi_hari_santri')
                    ->label('Riwayat percakapan santri disimpan (hari)')
                    ->helperText('Sebaiknya lebih singkat dari riwayat umum.')
                    ->numeric()->minValue(1)->maxValue(3650)
                    ->required(),
                Toggle::make('consent_ortu')
                    ->label('Persetujuan wali santri (consent) sudah dikumpulkan')
                    ->helperText('Prasyarat keras sebelum fitur santri dapat diaktifkan (Fase C).')
                    ->inline(false)
                    ->columnSpanFull(),
                Toggle::make('optin_riset')
                    ->label('Ikut serta riset perbaikan produk')
                    ->helperText('Bila aktif, yang dikumpulkan hanyalah data penggunaan ANONIM: jumlah pertanyaan, fitur yang dipakai, dan penilaian kepuasan — TANPA isi percakapan dan TANPA identitas santri/wali (PRD §6.4). Bawaan: tidak ikut.')
                    ->inline(false)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    // ------------------------------------------------------------------
    // Langkah 5 — Kanal, Jam & Bahasa
    // ------------------------------------------------------------------

    protected function langkahKanalJamBahasa(): Step
    {
        return Step::make('Kanal & Jam Layanan')
            ->icon('heroicon-o-clock')
            ->description('Kapan asisten melayani, dan apa pesannya di luar jam itu')
            ->afterValidation(fn () => $this->simpanLangkah())
            ->schema([
                Section::make('Chatbot WhatsApp')
                    ->description('Atur perilaku balasan otomatis asisten di WhatsApp.')
                    ->schema([
                        Toggle::make('mode_uji_wa')
                            ->label('Mode uji coba WhatsApp (balasan template, tanpa AI)')
                            ->helperText('AKTIF: setiap pesan masuk dibalas pesan template singkat tanpa melibatkan AI — aman untuk uji coba. NONAKTIF: asisten menjawab sungguhan (verifikasi wali, intent, lalu tanya-jawab). Disarankan tetap AKTIF sampai pesantren siap melayani lewat WhatsApp.')
                            ->inline(false),
                    ])
                    ->columnSpanFull(),
                TimePicker::make('jam_layanan.mulai')
                    ->label('Jam mulai layanan')
                    ->seconds(false)
                    ->helperText('Isi 00:00 sampai 23:59 untuk layanan 24 jam.')
                    ->required(),
                TimePicker::make('jam_layanan.selesai')
                    ->label('Jam selesai layanan')
                    ->seconds(false)
                    ->required(),
                Textarea::make('pesan_di_luar_jam')
                    ->label('Pesan di luar jam layanan')
                    ->helperText('Disampaikan apa adanya kepada penanya — mohon gunakan bahasa yang santun.')
                    ->rows(3)
                    ->maxLength(1000)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    // ------------------------------------------------------------------
    // Langkah 6 — Anggaran (+ Ringkasan Kebijakan di blade)
    // ------------------------------------------------------------------

    protected function langkahAnggaran(): Step
    {
        return Step::make('Anggaran')
            ->icon('heroicon-o-banknotes')
            ->description('Batas pemakaian kredit AI per bulan')
            ->schema([
                TextInput::make('anggaran_kredit_bulanan')
                    ->label('Batas kredit per bulan')
                    ->helperText('Kosongkan untuk tanpa batas khusus (mengikuti paket platform).')
                    ->numeric()->minValue(0)
                    ->nullable(),
                TextInput::make('ambang_alert_persen')
                    ->label('Peringatan pada pemakaian (%)')
                    ->helperText('Pengurus diberi tahu saat pemakaian menyentuh persentase ini.')
                    ->numeric()->minValue(1)->maxValue(100)
                    ->required(),
                Section::make('Ringkasan Kebijakan')
                    ->description('Bacalah ringkasan di bagian bawah halaman ini sebelum menekan "Ajukan ke Pimpinan". Ringkasan diperbarui otomatis mengikuti isian Anda.')
                    ->schema([])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    protected function bolehKelola(): bool
    {
        return Auth::user()?->can('ai.setting.kelola') ?? false;
    }

    /** Payload utuh = payload dasar ditimpa isian form saat ini. */
    public function payloadDariForm(): array
    {
        $state = $this->form->getState();

        $payload = array_replace($this->payloadDasar, $state);

        // Normalisasi tipe agar lolos validasi ketat service.
        $payload['blacklist_topik'] = array_values(array_filter(array_map(
            'strval',
            (array) ($payload['blacklist_topik'] ?? []),
        ), fn (string $t): bool => $t !== ''));

        // whitelist_kitab (gap-83 — dikelola mandiri pesantren): array string
        // rujukan, buang entri kosong agar lolos validasi (string, max:255).
        $payload['whitelist_kitab'] = array_values(array_filter(array_map(
            'strval',
            (array) ($payload['whitelist_kitab'] ?? []),
        ), fn (string $t): bool => $t !== ''));

        $payload['eskalasi_tujuan'] = [
            'penerima' => array_values(array_map('intval', (array) data_get($payload, 'eskalasi_tujuan.penerima', []))),
            'kanal' => (string) data_get($payload, 'eskalasi_tujuan.kanal', 'panel'),
            'sla_jam' => (int) data_get($payload, 'eskalasi_tujuan.sla_jam', 24),
        ];

        foreach ($payload['fitur_per_peran'] as $peran => $fiturs) {
            foreach ((array) $fiturs as $fitur => $aktif) {
                $payload['fitur_per_peran'][$peran][$fitur] = (bool) $aktif;
            }
        }

        foreach ((array) $payload['scope_data'] as $scope => $boleh) {
            $payload['scope_data'][$scope] = (bool) $boleh;
        }

        // TimePicker bisa mengembalikan H:i:s — service menuntut H:i.
        $payload['jam_layanan'] = [
            'mulai' => substr((string) data_get($payload, 'jam_layanan.mulai', '00:00'), 0, 5),
            'selesai' => substr((string) data_get($payload, 'jam_layanan.selesai', '23:59'), 0, 5),
        ];

        $payload['retensi_hari'] = (int) $payload['retensi_hari'];
        $payload['retensi_hari_santri'] = (int) $payload['retensi_hari_santri'];
        $payload['ambang_alert_persen'] = (int) $payload['ambang_alert_persen'];
        $payload['consent_ortu'] = (bool) $payload['consent_ortu'];
        $payload['optin_riset'] = (bool) $payload['optin_riset'];
        // gap-135 — toggle mode uji WA (fallback default true bila absen pada
        // payload dasar tenant lama → kompat-mundur, perilaku tak berubah).
        $payload['mode_uji_wa'] = (bool) ($payload['mode_uji_wa'] ?? true);

        $anggaran = $payload['anggaran_kredit_bulanan'] ?? null;
        $payload['anggaran_kredit_bulanan'] = ($anggaran === null || $anggaran === '')
            ? null
            : (float) $anggaran;

        return $payload;
    }

    /**
     * Autosave antar-langkah: draf yang sama diperbarui, tidak membuat
     * versi baru tiap langkah. Pengguna yang hanya punya `ai.setting.lihat`
     * tidak menyimpan apa-apa.
     */
    public function simpanLangkah(): void
    {
        if (! $this->bolehKelola()) {
            return;
        }

        try {
            $service = app(TenantSettingService::class);
            $payload = $this->payloadDariForm();
            $user = Auth::user();

            $draf = $this->drafVersi !== null
                ? $service->updateDraf($this->drafVersi, $payload, $user)
                : $service->simpanDraf($payload, $user);

            $this->drafVersi = $draf->versi;
        } catch (ValidationException|AuthorizationException $e) {
            Notification::make()
                ->title('Draf belum tersimpan')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }

    /** Tombol "Ajukan ke Pimpinan" (submit Wizard). */
    public function ajukan(): void
    {
        if (! $this->bolehKelola()) {
            Notification::make()
                ->title('Mohon maaf, Anda tidak memiliki izin mengelola pengaturan AI.')
                ->danger()
                ->send();

            return;
        }

        try {
            $service = app(TenantSettingService::class);
            $user = Auth::user();
            $payload = $this->payloadDariForm();

            $draf = $this->drafVersi !== null
                ? $service->updateDraf($this->drafVersi, $payload, $user)
                : $service->simpanDraf($payload, $user);

            $hasil = $service->ajukan($draf->versi, $user);
            $this->drafVersi = null;
            $this->payloadDasar = $hasil->payload;

            if ($hasil->status === AiTenantSetting::STATUS_PENDING_APPROVAL) {
                Notification::make()
                    ->title('Pengajuan terkirim')
                    ->body('Perubahan menyangkut kebijakan sensitif, sehingga menunggu persetujuan Kyai/Mudir. Pengaturan lama tetap berlaku sampai disetujui.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Pengaturan berlaku')
                    ->body('Perubahan tidak menyangkut kebijakan sensitif dan langsung diberlakukan.')
                    ->success()
                    ->send();
            }
        } catch (ValidationException|AuthorizationException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Pengajuan belum berhasil')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /** Ringkasan Kebijakan dari isian saat ini (dipakai blade). */
    public function ringkasanKebijakan(): array
    {
        $payload = array_replace($this->payloadDasar, (array) $this->data);

        return app(RingkasanKebijakan::class)->susun($payload);
    }

    /**
     * T-A012 — tautan bertanda tangan (signed URL) halaman ringkasan
     * kebijakan untuk pengajuan TERBARU yang masih menunggu keputusan.
     * Disalin admin lalu dikirim manual via WA-nya sendiri (keputusan sadar:
     * WaNotificationService terikat mesin broadcast + template ber-approval
     * Meta — bukan pesan satuan; TANPA jalur WA baru, Aturan Emas #5).
     * Tautan BACA-SAJA: persetujuan tetap wajib login + permission.
     */
    public function tautanRingkasanKyai(): ?string
    {
        $approval = AiApproval::query()
            ->where('status', AiApproval::STATUS_MENUNGGU)
            ->orderByDesc('id')
            ->first();

        if ($approval === null) {
            return null;
        }

        return URL::temporarySignedRoute(
            'ai.ringkasan.signed',
            now()->addHours($this->tautanRingkasanJam()),
            ['approval' => $approval->getKey()],
        );
    }

    /** Umur tautan ringkasan (jam) — dipakai blade untuk kalimat penjelas. */
    public function tautanRingkasanJam(): int
    {
        return max(1, (int) config('ai.settings.tautan_ringkasan_jam', 72));
    }

    // ------------------------------------------------------------------
    // gap-136 — Status Infrastruktur AI (READ-ONLY swa-periksa di panel)
    //
    // Menampilkan ringkasan yang sebelumnya hanya tersedia lewat CLI
    // `php artisan ai:health`: Ollama terjangkau + model, kedalaman antrian
    // queue 'ai', dan sisa anggaran kredit tenant. SEMUA logika memanggil
    // service yang SUDAH ADA (AiGateway::providerHealth, BudgetGuard::cek,
    // AiTenantBudget) — halaman ini hanya merangkai & menampilkan, TIDAK
    // mengubah logika kesehatan/anggaran/inferensi.
    //
    // Provisioning baris anggaran dilakukan idempoten (AiBudgetProvisioner
    // memakai insertOrIgnore) HANYA bila baris tenant belum ada — sehingga
    // status "BELUM ADA" tidak menetap untuk tenant yang valid.
    // ------------------------------------------------------------------

    /** Id tenant aktif (panel tenant) atau null bila konteks central. */
    protected function tenantIdKini(): ?string
    {
        return tenancy()->initialized ? (string) tenant()?->getTenantKey() : null;
    }

    /**
     * Ringkasan status infrastruktur AI untuk ditampilkan di blade.
     * Tiap blok toleran: kegagalan satu pemeriksaan tidak menggugurkan
     * blok lainnya maupun render halaman.
     *
     * @return array{
     *     provider: list<array{nama: string, ok: bool, detail: string, model: list<string>}>,
     *     antrian: array{label: string, ok: bool, detail: string},
     *     anggaran: array{tersedia: bool, ok: bool, killSwitch: bool, detail: string},
     * }
     */
    public function statusInfra(): array
    {
        return [
            'provider' => $this->statusProvider(),
            'antrian' => $this->statusAntrian(),
            'anggaran' => $this->statusAnggaran(),
        ];
    }

    /**
     * Kesehatan provider nyata (Ollama dst) + daftar model — memanggil
     * AiGateway::providerHealth() yang sama dengan ai:health.
     *
     * @return list<array{nama: string, ok: bool, detail: string, model: list<string>}>
     */
    protected function statusProvider(): array
    {
        try {
            $laporan = [];
            foreach (app(AiGateway::class)->providerHealth() as $nama => $health) {
                $laporan[] = [
                    'nama' => (string) $nama,
                    'ok' => (bool) ($health['ok'] ?? false),
                    'detail' => (string) ($health['detail'] ?? ''),
                    'model' => array_values(array_map('strval', (array) ($health['models'] ?? []))),
                ];
            }

            return $laporan;
        } catch (Throwable $e) {
            return [[
                'nama' => 'Provider AI',
                'ok' => false,
                'detail' => 'Tidak dapat diperiksa: '.$e->getMessage(),
                'model' => [],
            ]];
        }
    }

    /**
     * Kedalaman antrian queue 'ai' di Redis — read-only, meniru logika
     * laporan AiHealthCommand tanpa memodifikasi apa pun.
     *
     * @return array{label: string, ok: bool, detail: string}
     */
    protected function statusAntrian(): array
    {
        $queue = (string) config('ai.queue.name', 'ai');
        $queueConnection = (string) config('ai.queue.connection', 'redis-ai');

        if (! is_array(config("queue.connections.{$queueConnection}"))) {
            return [
                'label' => "Antrian '{$queue}'",
                'ok' => false,
                'detail' => "Koneksi queue '{$queueConnection}' belum dikonfigurasi.",
            ];
        }

        $connection = (string) config("queue.connections.{$queueConnection}.connection", 'default');

        try {
            $redis = Redis::connection($connection);
            $pending = (int) $redis->llen("queues:{$queue}");
            $delayed = (int) $redis->zcard("queues:{$queue}:delayed");
            $reserved = (int) $redis->zcard("queues:{$queue}:reserved");

            return [
                'label' => "Antrian '{$queue}'",
                'ok' => true,
                'detail' => sprintf('%d menunggu, %d tertunda, %d diproses', $pending, $delayed, $reserved),
            ];
        } catch (Throwable $e) {
            return [
                'label' => "Antrian '{$queue}'",
                'ok' => false,
                'detail' => 'Tidak terjangkau: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sisa anggaran kredit AI tenant. Memanggil BudgetGuard::cek() &
     * membaca AiTenantBudget (central) — sama dengan baris anggaran
     * ai:health. Bila baris belum ada, provisioning idempoten dijalankan
     * sekali (AiBudgetProvisioner) lalu dibaca ulang; tetap toleran.
     *
     * @return array{tersedia: bool, ok: bool, killSwitch: bool, detail: string}
     */
    protected function statusAnggaran(): array
    {
        $tenantId = $this->tenantIdKini();

        if ($tenantId === null) {
            return [
                'tersedia' => false,
                'ok' => false,
                'killSwitch' => false,
                'detail' => 'Konteks tenant tidak terdeteksi.',
            ];
        }

        try {
            $budget = AiTenantBudget::query()->where('tenant_id', $tenantId)->first();

            // Provisioning idempoten: hanya bila baris belum ada. Provisioner
            // memakai insertOrIgnore + tidak mengubah riwayat ledger berjalan.
            if ($budget === null) {
                app(AiBudgetProvisioner::class)->provision($tenantId);
                $budget = AiTenantBudget::query()->where('tenant_id', $tenantId)->first();
            }

            if ($budget === null) {
                return [
                    'tersedia' => false,
                    'ok' => false,
                    'killSwitch' => false,
                    'detail' => 'Baris anggaran belum dapat disiapkan (tabel metering AI mungkin belum termigrasi).',
                ];
            }

            $status = app(BudgetGuard::class)->cek($tenantId);

            if ($status->killSwitch) {
                return [
                    'tersedia' => true,
                    'ok' => false,
                    'killSwitch' => true,
                    'detail' => sprintf(
                        'Anggaran bulan ini habis (terpakai %s%%) — panggilan AI dibalas pesan top-up.',
                        number_format($status->persenTerpakai, 1, ',', '.'),
                    ),
                ];
            }

            $detail = $budget->anggaran_kredit_bulanan === null
                ? sprintf('Tanpa batas anggaran — terpakai %s kredit periode ini.', $this->angkaKredit($budget->terpakai_periode))
                : sprintf(
                    'Terpakai %s dari %s kredit (%s%%), peringatan di %d%%.',
                    $this->angkaKredit($budget->terpakai_periode),
                    $this->angkaKredit($budget->anggaran_kredit_bulanan),
                    number_format($status->persenTerpakai, 1, ',', '.'),
                    (int) $budget->ambang_alert_persen,
                );

            return [
                'tersedia' => true,
                'ok' => true,
                'killSwitch' => false,
                'detail' => $detail,
            ];
        } catch (Throwable $e) {
            return [
                'tersedia' => false,
                'ok' => false,
                'killSwitch' => false,
                'detail' => 'Tidak dapat diperiksa: '.$e->getMessage(),
            ];
        }
    }

    private function angkaKredit(int|float|string|null $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }
}
