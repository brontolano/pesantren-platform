<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Facades\Filament;
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
use Modules\Notifikasi\Services\WahaHealthService;
use Modules\Notifikasi\Support\NotifikasiSettings;

/**
 * Pengaturan saluran notifikasi (WhatsApp & Email) per tenant. Tiap field
 * dipetakan ke satu record Setting (key => value JSON) milik tenant aktif,
 * lalu dibaca oleh NotifikasiSettings di service WA/email & job broadcast.
 */
class PengaturanNotifikasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Pengaturan Notifikasi';

    protected static ?string $title = 'Pengaturan Notifikasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'notifikasi/pengaturan';

    protected string $view = 'filament.pages.pengaturan-notifikasi';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // gap-132: halaman setting harus SELALU terjangkau (samakan dgn
        // PengaturanBranding) — tak digerbang Mode Sederhana.
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'notif_wa_enabled' => NotifikasiSettings::waEnabled(),
            'notif_email_enabled' => NotifikasiSettings::emailEnabled(),
            'notif_wa_signature' => NotifikasiSettings::signature(),
            'notif_broadcast_interval' => NotifikasiSettings::broadcastInterval(),
            'notif_wa_webhook_url' => NotifikasiSettings::reveal(data_get(Setting::getTenantValue(NotifikasiSettings::KEY_WA_WEBHOOK), 'url')),
            'notif_wa_api_key' => NotifikasiSettings::reveal(data_get(Setting::getTenantValue(NotifikasiSettings::KEY_WA_API_KEY), 'key')),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Saluran WhatsApp')
                    ->description('Notifikasi WhatsApp dikirim lewat pipa n8n → WAHA. Nonaktifkan untuk menghentikan sementara semua pesan WA tenant ini.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Toggle::make('notif_wa_enabled')
                            ->label('Aktifkan notifikasi WhatsApp')
                            ->default(true)
                            ->helperText('Jika mati: OTP, pemberitahuan, dan broadcast WA tidak dikirim.'),
                        Placeholder::make('waha_health_status')
                            ->label('Status sesi WhatsApp (WAHA)')
                            ->content(fn (): HtmlString => new HtmlString($this->wahaHealthBadge())),
                        TextInput::make('notif_wa_webhook_url')
                            ->label('URL webhook n8n khusus tenant (opsional)')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('http://n8n:5678/webhook/ppdb-wa')
                            ->helperText('Kosongkan untuk memakai pipa WA bawaan platform. Isi bila tenant ini punya alur n8n/nomor pengirim sendiri. Disimpan terenkripsi.'),
                        TextInput::make('notif_wa_api_key')
                            ->label('API key WAHA (opsional)')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Isi bila instance WAHA dijalankan dengan WAHA_API_KEY (dikirim sebagai header X-Api-Key). Disimpan terenkripsi.'),
                        Textarea::make('notif_wa_signature')
                            ->label('Tanda tangan / footer pesan')
                            ->rows(2)
                            ->maxLength(280)
                            ->placeholder('-- Pondok Pesantren Asy-Syifaa')
                            ->helperText('Opsional. Ditambahkan di akhir setiap pesan WhatsApp.'),
                    ]),

                Section::make('Broadcast')
                    ->description('Pengiriman massal diberi jeda antar-pesan agar tidak kena rate-limit / blokir WhatsApp.')
                    ->icon('heroicon-o-megaphone')
                    ->schema([
                        TextInput::make('notif_broadcast_interval')
                            ->label('Jeda antar-pesan (detik)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(300)
                            ->default(10)
                            ->required()
                            ->suffix('detik')
                            ->helperText('Rekomendasi 10 detik. Maksimal 300 detik.'),
                    ]),

                Section::make('Saluran Email')
                    ->description('Notifikasi email memakai konfigurasi SMTP aplikasi.')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        Toggle::make('notif_email_enabled')
                            ->label('Aktifkan notifikasi email')
                            ->default(true)
                            ->helperText($this->smtpStatusHint()),
                    ]),
            ]);
    }

    /**
     * Status server email (config mail global) agar staf tahu: mengaktifkan toggle
     * benar-benar mengirim, atau hanya tersimpan tanpa efek (gap 138: ON gagal diam-diam).
     */
    private function smtpStatusHint(): string
    {
        $mailer = (string) config('mail.default');
        $terkonfigurasi = match (true) {
            in_array($mailer, ['log', 'array', ''], true) => false,
            $mailer === 'smtp' => filled(config('mail.mailers.smtp.host')),
            default => true,
        };

        if (! $terkonfigurasi) {
            return '⚠ Server email BELUM dikonfigurasi (MAIL_MAILER='.($mailer ?: 'kosong')
                .'). Mengaktifkan ini TIDAK mengirim email apa pun sampai SMTP diatur di server — hubungi admin platform.';
        }

        $host = config('mail.mailers.smtp.host');

        return 'Server email AKTIF (mailer: '.$mailer.($host ? ', '.$host : '').'). Email notifikasi akan dikirim.';
    }

    /**
     * Badge status sesi WAHA (WORKING / mati) untuk halaman ini. Fail-soft:
     * probe timeout pendek & error ditelan di WahaHealthService — badge tak
     * pernah menggagalkan render halaman (regresi 500).
     */
    private function wahaHealthBadge(): string
    {
        $r = app(WahaHealthService::class)->check(3);

        if (! ($r['configured'] ?? false)) {
            return '<span style="color:#6b7280">◯ WAHA langsung tidak dikonfigurasi — notifikasi lewat pipa n8n. Status sesi tak dapat diperiksa dari sini.</span>';
        }

        if ($r['healthy'] ?? false) {
            return '<span style="color:#16a34a;font-weight:600">● WORKING</span> — sesi WhatsApp aktif dan siap mengirim.';
        }

        $status = e((string) ($r['status'] ?? 'TIDAK TERJANGKAU'));
        $error = e((string) ($r['error'] ?? 'Sesi tidak sehat.'));

        return '<span style="color:#dc2626;font-weight:600">● '.$status.'</span> — '.$error
            .' WhatsApp mungkin tidak terkirim (nomor logout / QR perlu di-scan ulang).';
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $payloads = [
            NotifikasiSettings::KEY_WA_ENABLED => ['enabled' => (bool) ($data['notif_wa_enabled'] ?? true)],
            NotifikasiSettings::KEY_EMAIL_ENABLED => ['enabled' => (bool) ($data['notif_email_enabled'] ?? true)],
            NotifikasiSettings::KEY_WA_SIGNATURE => ['text' => $data['notif_wa_signature'] ?? null],
            NotifikasiSettings::KEY_BROADCAST_INTERVAL => ['seconds' => max(0, min(300, (int) ($data['notif_broadcast_interval'] ?? 10)))],
            // Kredensial sensitif disimpan TERENKRIPSI (Crypt). Nilai lama plaintext
            // tetap terbaca via NotifikasiSettings::reveal() (kompat-mundur).
            NotifikasiSettings::KEY_WA_WEBHOOK => ['url' => NotifikasiSettings::conceal($data['notif_wa_webhook_url'] ?? null)],
            NotifikasiSettings::KEY_WA_API_KEY => ['key' => NotifikasiSettings::conceal($data['notif_wa_api_key'] ?? null)],
        ];

        foreach ($payloads as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()
            ->title('Pengaturan notifikasi disimpan')
            ->success()
            ->send();
    }
}
