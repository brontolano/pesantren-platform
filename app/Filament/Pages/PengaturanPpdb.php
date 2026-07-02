<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\PPDB\Filament\Resources\GelombangPpdbResource;

/**
 * gap-125 — Halaman Pengaturan PPDB tersendiri (rumah kanonik PPDB).
 *
 * ADDITIVE: membaca/menulis SETTING KEY YANG SAMA dengan blok admission di
 * PengaturanBranding (admission_* + ppdb_form_fields). TIDAK migrasi/ganti nama
 * key, TIDAK mengubah PengaturanBranding. Bentuk wrapper value ditiru PERSIS
 * (admission_* → ['text'=>...], admission_inline_enabled → ['enabled'=>bool],
 * ppdb_form_fields → ['fields'=>[]]) agar reader existing
 * (PpdbFormFieldConfigurationService, LandingRegistrationController) tetap benar.
 *
 * Tujuan transition: staf punya satu tempat PPDB. Master Gelombang dijelaskan
 * + ditautkan ke Resource gelombang-ppdb.
 */
class PengaturanPpdb extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Pengaturan PPDB';

    protected static string|\UnitEnum|null $navigationGroup = 'Pendaftaran';

    protected static ?string $title = 'Pengaturan PPDB';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'pengaturan-ppdb';

    protected string $view = 'filament.pages.pengaturan-ppdb';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $settings = Setting::query()->get()->keyBy('key');

        // gap-131 (pola sama PengaturanBranding): default Tahun Ajaran mengikuti
        // PeriodeAkademik aktif bila ada, fallback ke nilai tersimpan, lalu '2026/2027'.
        $defaultAcademicYear = data_get($settings->get('admission_academic_year')?->value, 'text', '2026/2027');
        try {
            $activeAcademicYear = PeriodeAkademik::query()
                ->where('is_active', true)
                ->value('tahun_akademik');
            if (is_string($activeAcademicYear) && trim($activeAcademicYear) !== '') {
                $defaultAcademicYear = $activeAcademicYear;
            }
        } catch (\Throwable) {
            // abaikan — modul Akademik mungkin belum ter-migrasi
        }

        $this->form->fill([
            'ppdb_form_fields' => data_get($settings->get('ppdb_form_fields')?->value, 'fields', []),
            'admission_inline_enabled' => (bool) data_get($settings->get('admission_inline_enabled')?->value, 'enabled', false),
            'admission_academic_year' => $defaultAcademicYear,
            'admission_admin_whatsapp' => data_get($settings->get('admission_admin_whatsapp')?->value, 'text'),
            'admission_bank_name' => data_get($settings->get('admission_bank_name')?->value, 'text'),
            'admission_account_number' => data_get($settings->get('admission_account_number')?->value, 'text'),
            'admission_account_name' => data_get($settings->get('admission_account_name')?->value, 'text'),
            'admission_transfer_amount' => data_get($settings->get('admission_transfer_amount')?->value, 'text'),
            'admission_transfer_instructions' => data_get($settings->get('admission_transfer_instructions')?->value, 'text'),
            'admission_success_message' => data_get($settings->get('admission_success_message')?->value, 'text'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Master Gelombang')
                    ->description('Gelombang PPDB terstruktur dikelola sebagai master tersendiri. Tambah/ubah gelombang (tahun ajaran, tanggal buka/tutup, kuota, biaya referensi) di menu "Gelombang PPDB". Master ini bersifat referensi dan belum di-wire otomatis ke formulir pendaftaran publik.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Placeholder::make('gelombang_link')
                            ->label('')
                            ->content(new HtmlString(
                                '<a href="'.e(static::gelombangUrl()).'" class="fi-link inline-flex items-center gap-1 font-medium text-primary-600 underline dark:text-primary-400">Buka menu Gelombang PPDB &rarr;</a>'
                            )),
                    ]),

                Section::make('Konfigurasi Field Formulir PPDB')
                    ->description('Atur mode tiap field formulir pendaftaran. Isi nilai dengan: required, optional, hidden, atau staf (diisi panitia). Sama dengan konfigurasi di Settings & Branding — disimpan ke kunci yang sama.')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        KeyValue::make('ppdb_form_fields')
                            ->label('Konfigurasi Field PPDB')
                            ->keyLabel('Field')
                            ->valueLabel('Mode')
                            ->addActionLabel('Tambah field'),
                    ]),

                Section::make('Pendaftaran & Informasi Transfer')
                    ->description('Pengaturan form pendaftaran inline di landing page, admin WhatsApp, dan informasi rekening transfer. Nilai-nilai ini adalah informasi tampilan/notifikasi — tidak memposting transaksi keuangan.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Toggle::make('admission_inline_enabled')
                            ->label('Aktifkan form pendaftaran inline di landing page')
                            ->inline(false),
                        TextInput::make('admission_academic_year')
                            ->label('Tahun Ajaran')
                            ->maxLength(20),
                        TextInput::make('admission_admin_whatsapp')
                            ->label('WhatsApp Admin')
                            ->helperText('Nomor tujuan notifikasi pendaftar baru.'),
                        TextInput::make('admission_bank_name')
                            ->label('Nama Bank'),
                        TextInput::make('admission_account_number')
                            ->label('Nomor Rekening'),
                        TextInput::make('admission_account_name')
                            ->label('Atas Nama Rekening'),
                        TextInput::make('admission_transfer_amount')
                            ->label('Nominal Transfer')
                            ->helperText('Boleh angka atau teks, misalnya Rp350.000. Informasi tampilan saja.'),
                        Textarea::make('admission_transfer_instructions')
                            ->label('Instruksi Transfer')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('admission_success_message')
                            ->label('Pesan sukses setelah submit')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Tiru PERSIS bentuk wrapper value dari PengaturanBranding agar reader
        // existing tetap baca benar. JANGAN ganti nama key / bentuk wrapper.
        $payloads = [
            'ppdb_form_fields' => ['fields' => $data['ppdb_form_fields'] ?? []],
            'admission_inline_enabled' => ['enabled' => (bool) ($data['admission_inline_enabled'] ?? false)],
            'admission_academic_year' => ['text' => $data['admission_academic_year'] ?? '2026/2027'],
            'admission_admin_whatsapp' => ['text' => $data['admission_admin_whatsapp'] ?? null],
            'admission_bank_name' => ['text' => $data['admission_bank_name'] ?? null],
            'admission_account_number' => ['text' => $data['admission_account_number'] ?? null],
            'admission_account_name' => ['text' => $data['admission_account_name'] ?? null],
            'admission_transfer_amount' => ['text' => $data['admission_transfer_amount'] ?? null],
            'admission_transfer_instructions' => ['text' => $data['admission_transfer_instructions'] ?? null],
            'admission_success_message' => ['text' => $data['admission_success_message'] ?? null],
        ];

        foreach ($payloads as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()
            ->title('Pengaturan PPDB disimpan')
            ->success()
            ->send();
    }

    /**
     * URL Resource Gelombang PPDB (slug eksplisit gelombang-ppdb). Dibungkus
     * try/catch agar halaman tetap aman bila resource belum terdaftar di panel.
     */
    protected static function gelombangUrl(): string
    {
        try {
            return GelombangPpdbResource::getUrl('index');
        } catch (\Throwable) {
            return (string) url('/admin/gelombang-ppdb');
        }
    }
}
