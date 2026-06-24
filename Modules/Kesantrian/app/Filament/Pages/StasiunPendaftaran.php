<?php

namespace Modules\Kesantrian\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\EnrollmentService;
use Modules\Kesantrian\Services\NisGeneratorService;
use Modules\Kesantrian\Services\SantriAccountService;

/**
 * Stasiun Pendaftaran Santri — loket penginputan satu-per-satu (pola e-KTP):
 * cari santri / terbitkan NIS otomatis, lalu checklist: foto webcam, tanda
 * tangan, sampel tulisan (Arab/Indonesia), kartu NFC, PIN, cetak kartu.
 * Semua tulis lewat EnrollmentService / NisGeneratorService / SantriAccountService.
 */
class StasiunPendaftaran extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Stasiun Pendaftaran';

    protected static ?string $title = 'Stasiun Pendaftaran Santri';

    protected static string|\UnitEnum|null $navigationGroup = 'Santri';

    protected static ?int $navigationSort = 19;

    protected static ?string $slug = 'kesantrian/stasiun-pendaftaran';

    protected string $view = 'filament.pages.stasiun-pendaftaran';

    // ── State ──
    public string $cari = '';

    public ?int $santriId = null;

    // Form santri baru
    public string $baruNama = '';

    public string $baruJk = 'L';

    public string $baruTanggalLahir = '';

    // Input langkah
    public string $nfcUid = '';

    public string $pin = '';

    /** Baris ground-truth default formulir tulisan (Fase 2; Arab menunggu pilihan ustadz). */
    public const LATIN_LINES = [
        'Saya santri yang rajin belajar dan berakhlak mulia.',
        'abcdefghijklmnopqrstuvwxyz 0123456789',
        'Pondok pesantren adalah rumah kedua bagi para santri.',
    ];

    public const ARAB_LINES = [
        'بِسْمِ اللهِ الرَّحْمٰنِ الرَّحِيْمِ',
        'ا ب ت ث ج ح خ د ذ ر ز س ش ص ض ط ظ ع غ ف ق ك ل م ن و ه ي',
        '٠ ١ ٢ ٣ ٤ ٥ ٦ ٧ ٨ ٩',
    ];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('manage_santri') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    // ── Computed (dipakai blade) ──
    public function getSantriProperty(): ?Santri
    {
        return $this->santriId ? Santri::query()->find($this->santriId) : null;
    }

    public function getHasilCariProperty()
    {
        if (strlen(trim($this->cari)) < 2) {
            return collect();
        }

        return Santri::query()
            ->where(fn ($q) => $q->where('nama', 'ilike', '%'.trim($this->cari).'%')
                ->orWhere('nis', 'ilike', '%'.trim($this->cari).'%'))
            ->orderBy('nama')->limit(8)->get();
    }

    public function getStatusStepsProperty(): array
    {
        return $this->santri ? app(EnrollmentService::class)->status($this->santri) : [];
    }

    public function getPreviewNisProperty(): string
    {
        return app(NisGeneratorService::class)->preview();
    }

    public function getAntrianHariIniProperty()
    {
        return Santri::query()
            ->whereHas('enrollments', fn ($q) => $q->whereDate('captured_at', today()))
            ->withCount(['enrollments as selesai_count' => fn ($q) => $q->where('status', 'done')])
            ->orderByDesc('updated_at')->limit(10)->get();
    }

    // ── Aksi ──
    public function pilih(int $id): void
    {
        $this->santriId = $id;
        $this->cari = '';
        $this->nfcUid = '';
        $this->pin = '';
    }

    public function buatBaru(): void
    {
        $this->validate([
            'baruNama' => 'required|string|min:3|max:255',
            'baruJk' => 'required|in:L,P',
            'baruTanggalLahir' => 'required|date',
        ], [], ['baruNama' => 'nama', 'baruJk' => 'jenis kelamin', 'baruTanggalLahir' => 'tanggal lahir']);

        try {
            $santri = app(NisGeneratorService::class)->createSantri([
                'nama' => trim($this->baruNama),
                'jenis_kelamin' => $this->baruJk,
                'tanggal_lahir' => $this->baruTanggalLahir,
                'status' => 'aktif',
            ]);
            $this->reset('baruNama', 'baruTanggalLahir');
            $this->pilih($santri->id);
            Notification::make()->title("Santri terdaftar — NIS {$santri->nis}")->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Gagal membuat santri: '.$e->getMessage())->danger()->send();
        }
    }

    public function simpanFoto(string $base64): void
    {
        $this->jalankan(fn (EnrollmentService $svc) => $svc->simpanFoto($this->santri, $base64), 'Foto tersimpan');
    }

    public function simpanTtd(string $svg, string $png): void
    {
        $this->jalankan(fn (EnrollmentService $svc) => $svc->simpanTtd($this->santri, $svg, $png), 'Tanda tangan tersimpan');
    }

    public function tautkanNfc(): void
    {
        $uid = $this->nfcUid;
        $this->nfcUid = '';
        $this->jalankan(fn (EnrollmentService $svc) => $svc->tautkanNfc($this->santri, $uid), 'Kartu NFC tertaut');
    }

    public function setPin(): void
    {
        $this->validate(['pin' => 'required|digits:6']);
        $santri = $this->santri;
        if (! $santri) {
            return;
        }

        try {
            app(SantriAccountService::class)->setPin($santri, $this->pin);
            app(SantriAccountService::class)->tautkanWali($santri->fresh());
            app(EnrollmentService::class)->catat($santri, 'pin_qr', 'done');
            $this->pin = '';
            Notification::make()->title('PIN tersimpan — santri bisa login NIS + PIN')->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Gagal: '.$e->getMessage())->danger()->send();
        }
    }

    public function simpanSampel(string $script, string $base64): void
    {
        $lines = $script === 'arab' ? self::ARAB_LINES : self::LATIN_LINES;
        $this->jalankan(
            fn (EnrollmentService $svc) => $svc->simpanSampelTulisan($this->santri, $script, $base64, $lines),
            'Sampel tulisan '.($script === 'arab' ? 'Arab' : 'Indonesia').' tersimpan',
        );
    }

    public function lewati(string $step): void
    {
        $this->jalankan(fn (EnrollmentService $svc) => $svc->catat($this->santri, $step, 'skipped'), 'Langkah dilewati');
    }

    public function cetakKartu()
    {
        $santri = $this->santri;
        if (! $santri) {
            return null;
        }
        app(EnrollmentService::class)->catat($santri, 'cetak_kartu', 'done');

        return \App\Filament\Resources\Santri\SantriResource::unduhIdCard(collect([$santri]));
    }

    private function jalankan(callable $fn, string $sukses): void
    {
        if (! $this->santri) {
            Notification::make()->title('Pilih santri dulu.')->warning()->send();

            return;
        }

        try {
            $fn(app(EnrollmentService::class));
            Notification::make()->title($sukses)->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Gagal: '.$e->getMessage())->danger()->send();
        }
    }
}
