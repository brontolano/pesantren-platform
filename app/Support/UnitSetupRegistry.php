<?php

namespace App\Support;

use App\Filament\Pages\IntegrasiApi;
use App\Filament\Pages\PengaturanBranding;
use App\Filament\Resources\Santri\SantriResource;
use App\Models\Setting;
use App\Models\TenantApiKey;
use App\Models\User;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use Modules\Akademik\Filament\Resources\Lembaga\LembagaResource;
use Modules\Akademik\Filament\Resources\Pelajaran\PelajaranResource;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\PeriodeAkademikResource;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\PenempatanAsramaResource;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\CMS\Filament\Resources\Article\ArticleResource;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesehatan\Filament\Resources\ObatResource;
use Modules\Kesehatan\Models\Obat;
use Modules\Keuangan\Filament\Resources\MetodePembayaran\MetodePembayaranResource;
use Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource;
use Modules\Keuangan\Filament\Resources\Tagihan\TagihanResource;
use Modules\Keuangan\Models\MetodePembayaran;
use Modules\Keuangan\Models\SkemaBiaya;
use Modules\Keuangan\Models\Tagihan;
use Modules\Ledger\Filament\Resources\AkunResource;
use Modules\Ledger\Filament\Resources\PeriodeAkuntansiResource;
use Modules\Ledger\Filament\Resources\SumberDanaResource;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\PeriodeAkuntansi;
use Modules\Ledger\Models\SumberDana;
use Modules\Mutu\Filament\Resources\MutuSasaranResource;
use Modules\Mutu\Models\MutuSasaran;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Modules\Payroll\Filament\Resources\Jabatan\JabatanResource;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Pembiayaan\Filament\Resources\AsetResource;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pengadaan\Filament\Resources\Supplier\SupplierResource;
use Modules\Pengadaan\Models\Supplier;
use Modules\PPDB\Filament\Resources\JadwalUjianResource;
use Modules\PPDB\Filament\Resources\PaketSoal\PaketSoalResource;
use Modules\PPDB\Models\JadwalUjian;
use Modules\PPDB\Models\PaketSoal;
use Modules\Tahfidz\Filament\Resources\Halaqah\HalaqahResource;
use Modules\Tahfidz\Models\Halaqah;

/**
 * Sumber kebenaran TUNGGAL untuk "onboarding bertahap" per unit.
 *
 * Mendefinisikan, untuk tiap unit, urutan langkah setup + cek "selesai" (membaca
 * state nyata DB) + tautan ke halaman terkait. Dipakai bersama oleh:
 *  - Pusat Setup (hub) — progres per unit
 *  - Wizard per unit — langkah & status
 *  - (opsional) widget dashboard
 * Sehingga TIDAK ada logika status yang diduplikasi di banyak tempat.
 *
 * Prinsip: sederhana & toleran — semua cek dibungkus try/catch; gagal cek =
 * dianggap "belum" (tak pernah melempar, tak pernah menggagalkan halaman).
 */
class UnitSetupRegistry
{
    /**
     * @return array<string, array{
     *   label:string, icon:string, permission:string,
     *   steps: list<array{key:string, label:string, desc:string, done:bool, url:?string}>
     * }>
     */
    public static function units(): array
    {
        return [
            // 1 — KESANTRIAN (fold: asrama + kesehatan, sesuai domain navigasi)
            'kesantrian' => [
                'label' => 'Kesantrian',
                'icon' => 'heroicon-o-user-group',
                'permission' => 'manage_santri',
                'steps' => [
                    self::step('santri', 'Data santri', 'Impor dari Excel atau tambah manual.',
                        self::adaData(Santri::class),
                        self::resourceUrl(SantriResource::class)),
                    self::step('wali', 'Akun wali', 'Buat akun wali (login pakai no. HP).',
                        self::adaWali(),
                        self::resourceUrl(SantriResource::class)),
                    self::step('asrama', 'Penempatan asrama', 'Blok/kamar + penempatan santri (bisa impor).',
                        self::adaData(PenempatanAsrama::class),
                        self::resourceUrl(PenempatanAsramaResource::class)),
                    self::step('obat', 'Master obat (UKS)', 'Daftar obat (bisa impor).',
                        self::adaData(Obat::class),
                        self::resourceUrl(ObatResource::class)),
                ],
            ],
            // 2 — PPDB
            'ppdb' => [
                'label' => 'PPDB',
                'icon' => 'heroicon-o-clipboard-document-list',
                'permission' => 'view_santri',
                'steps' => [
                    self::step('jadwal_ujian', 'Jadwal ujian', 'Jadwal ujian seleksi.',
                        self::adaData(JadwalUjian::class),
                        self::resourceUrl(JadwalUjianResource::class)),
                    self::step('bank_soal', 'Bank/paket soal', 'Paket soal ujian masuk.',
                        self::adaData(PaketSoal::class),
                        self::resourceUrl(PaketSoalResource::class)),
                ],
            ],
            // 3 — AKADEMIK (Tahfidz dipisah, selaras navigasi)
            'akademik' => [
                'label' => 'Akademik',
                'icon' => 'heroicon-o-academic-cap',
                'permission' => 'manage_akademik',
                'steps' => [
                    self::step('lembaga', 'Lembaga / unit', 'Minimal satu lembaga (mis. Madrasah).',
                        self::adaData(Lembaga::class),
                        self::resourceUrl(LembagaResource::class)),
                    self::step('periode', 'Periode akademik aktif', 'Tahun ajaran yang sedang berjalan.',
                        self::adaPeriodeAktif(),
                        self::resourceUrl(PeriodeAkademikResource::class)),
                    self::step('pelajaran', 'Pelajaran', 'Daftar mata pelajaran (bisa impor).',
                        self::adaData(Pelajaran::class),
                        self::resourceUrl(PelajaranResource::class)),
                    self::step('jadwal', 'Jadwal pelajaran', 'Jadwal per kelas (bisa impor).',
                        self::adaData(JadwalPelajaran::class),
                        self::resourceUrl(JadwalPelajaranResource::class)),
                ],
            ],
            // 3b — TAHFIDZ
            'tahfidz' => [
                'label' => 'Tahfidz',
                'icon' => 'heroicon-o-book-open',
                'permission' => 'manage_akademik',
                'steps' => [
                    self::step('halaqah', 'Halaqah tahfidz', 'Kelompok hafalan + musyrif (bisa impor).',
                        self::adaData(Halaqah::class),
                        self::resourceUrl(HalaqahResource::class)),
                ],
            ],
            // 4 — KEUANGAN (operasional)
            'keuangan' => [
                'label' => 'Keuangan',
                'icon' => 'heroicon-o-banknotes',
                'permission' => 'manage_keuangan',
                'steps' => [
                    self::step('skema_biaya', 'Skema biaya', 'Template tagihan (SPP, dll).',
                        self::adaData(SkemaBiaya::class),
                        self::resourceUrl(SkemaBiayaResource::class)),
                    self::step('metode', 'Metode pembayaran', 'Tunai/transfer/QRIS, dll.',
                        self::adaData(MetodePembayaran::class),
                        self::resourceUrl(MetodePembayaranResource::class)),
                    self::step('tagihan', 'Tagihan / SPP', 'Buat atau impor tagihan santri.',
                        self::adaData(Tagihan::class),
                        self::resourceUrl(TagihanResource::class)),
                ],
            ],
            // 5 — AKUNTANSI & PAJAK (fondasi uang)
            'akuntansi' => [
                'label' => 'Akuntansi & Pajak',
                'icon' => 'heroicon-o-calculator',
                'permission' => 'manage_ledger',
                'steps' => [
                    self::step('coa', 'Bagan akun (COA)', 'Otomatis ter-seed; tinjau bila perlu.',
                        self::adaData(Akun::class),
                        self::resourceUrl(AkunResource::class)),
                    self::step('sumber_dana', 'Sumber dana', 'Klasifikasi sumber dana.',
                        self::adaData(SumberDana::class),
                        self::resourceUrl(SumberDanaResource::class)),
                    self::step('periode_akuntansi', 'Periode akuntansi', 'Periode buku berjalan.',
                        self::adaData(PeriodeAkuntansi::class),
                        self::resourceUrl(PeriodeAkuntansiResource::class)),
                ],
            ],
            // 6 — KEPEGAWAIAN (fold: sdm)
            'kepegawaian' => [
                'label' => 'Kepegawaian',
                'icon' => 'heroicon-o-briefcase',
                'permission' => 'run_payroll',
                'steps' => [
                    self::step('jabatan', 'Jabatan', 'Otomatis ter-seed; sesuaikan bila perlu.',
                        self::adaData(Jabatan::class),
                        self::resourceUrl(JabatanResource::class)),
                    self::step('pegawai', 'Data pegawai', 'Guru & karyawan (bisa impor).',
                        self::adaData(Employee::class),
                        self::resourceUrl(EmployeeResource::class)),
                ],
            ],
            // 7 — KELEMBAGAAN & KOMUNIKASI
            'kelembagaan' => [
                'label' => 'Kelembagaan & Komunikasi',
                'icon' => 'heroicon-o-building-office-2',
                'permission' => 'manage_tenant_settings',
                'steps' => [
                    self::step('website', 'Konten website', 'Artikel terbit / galeri / profil pondok.',
                        self::adaKontenWebsite(),
                        self::resourceUrl(ArticleResource::class)),
                ],
            ],
            // 7b — SARANA & LOGISTIK (selaras grup navigasi baru)
            'sarana_logistik' => [
                'label' => 'Sarana & Logistik',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'permission' => 'manage_pembiayaan',
                'steps' => [
                    self::step('supplier', 'Supplier', 'Master pemasok (bisa impor).',
                        self::adaData(Supplier::class),
                        self::resourceUrl(SupplierResource::class)),
                    self::step('aset', 'Aset', 'Daftar aset/inventaris (bisa impor).',
                        self::adaData(Aset::class),
                        self::resourceUrl(AsetResource::class)),
                ],
            ],
            // 8 — PENGATURAN & MUTU (fondasi konfigurasi)
            'pengaturan' => [
                'label' => 'Pengaturan & Mutu',
                'icon' => 'heroicon-o-cog-6-tooth',
                'permission' => 'manage_tenant_settings',
                'steps' => [
                    self::step('identitas', 'Identitas & branding', 'Nama, logo, kontak pondok.',
                        self::settingTerisi('branding_name'),
                        self::pageUrl(PengaturanBranding::class)),
                    self::step('integrasi', 'Integrasi API/WA', 'Kunci API untuk WhatsApp/notifikasi.',
                        self::adaKunciApiAktif(),
                        self::pageUrl(IntegrasiApi::class)),
                    self::step('mutu', 'Sasaran mutu', 'Target/KPI mutu pesantren.',
                        self::adaData(MutuSasaran::class),
                        self::resourceUrl(MutuSasaranResource::class)),
                ],
            ],
        ];
    }

    /**
     * units() yang di-memo per-request (di-cache di container yang fresh tiap
     * request/test). Dipakai jalur PANAS (gerbang nav memanggil kesiapan modul
     * untuk ~115 resource) agar cek DB unit tak diulang berkali-kali.
     *
     * @return array<string, array{label:string, icon:string, permission:string, steps: list<array{key:string, label:string, desc:string, done:bool, url:?string}>}>
     */
    public static function unitsCached(): array
    {
        // Ber-tenant-key: stancl tidak membersihkan binding container saat ganti
        // tenant, jadi tanpa key per-tenant flag "done" tenant A bisa bocor ke
        // tenant B di proses long-running (queue worker / runForMultiple).
        $tenantId = tenancy()->initialized ? (tenant('id') ?? 'central') : 'central';
        $key = '__unit_setup_registry_units_'.$tenantId;

        if (app()->bound($key)) {
            return app()->make($key);
        }
        $units = self::units();
        app()->instance($key, $units);

        return $units;
    }

    /** Ringkasan progres satu unit: {selesai, total, persen, tuntas}. */
    public static function progress(string $kode): array
    {
        $unit = self::units()[$kode] ?? null;
        if ($unit === null) {
            return ['selesai' => 0, 'total' => 0, 'persen' => 0, 'tuntas' => false];
        }

        $total = count($unit['steps']);
        $selesai = collect($unit['steps'])->where('done', true)->count();

        return [
            'selesai' => $selesai,
            'total' => $total,
            'persen' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
            'tuntas' => $total > 0 && $selesai === $total,
        ];
    }

    /**
     * @return array{key:string, label:string, desc:string, done:bool, url:?string}
     */
    private static function step(string $key, string $label, string $desc, bool $done, ?string $url): array
    {
        return ['key' => $key, 'label' => $label, 'desc' => $desc, 'done' => $done, 'url' => $url];
    }

    private static function settingTerisi(string $key): bool
    {
        try {
            $v = Setting::getTenantStringValue($key);

            return is_string($v) && trim($v) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    private static function adaData(string $model): bool
    {
        try {
            return class_exists($model) && $model::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function adaPeriodeAktif(): bool
    {
        try {
            $m = PeriodeAkademik::class;

            return class_exists($m) && $m::query()->where('is_active', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function adaWali(): bool
    {
        try {
            return User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'wali'))
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Konten website siap = ada artikel TERBIT atau galeri (selaras OnboardingChecklist). */
    private static function adaKontenWebsite(): bool
    {
        try {
            $a = Article::class;
            $g = Gallery::class;

            return (class_exists($a) && $a::query()->where('is_published', true)->exists())
                || (class_exists($g) && $g::query()->exists());
        } catch (\Throwable) {
            return false;
        }
    }

    /** Integrasi API/WA siap = ada kunci API aktif (sesuai yang dikelola halaman IntegrasiApi). */
    private static function adaKunciApiAktif(): bool
    {
        try {
            $m = TenantApiKey::class;

            return class_exists($m) && $m::query()->whereNull('revoked_at')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function resourceUrl(string $resource, string $route = 'index'): ?string
    {
        try {
            return class_exists($resource) ? $resource::getUrl($route) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function pageUrl(string $page): ?string
    {
        try {
            return class_exists($page) ? $page::getUrl() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
