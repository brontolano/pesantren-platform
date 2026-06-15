<?php

namespace App\Support;

use App\Models\Setting;

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
                        self::adaData(\Modules\Kesantrian\Models\Santri::class),
                        self::resourceUrl(\App\Filament\Resources\Santri\SantriResource::class)),
                    self::step('wali', 'Akun wali', 'Buat akun wali (login pakai no. HP).',
                        self::adaWali(),
                        self::resourceUrl(\App\Filament\Resources\Santri\SantriResource::class)),
                    self::step('asrama', 'Penempatan asrama', 'Blok/kamar + penempatan santri (bisa impor).',
                        self::adaData(\Modules\Asrama\Models\PenempatanAsrama::class),
                        self::resourceUrl(\App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource::class)),
                    self::step('obat', 'Master obat (UKS)', 'Daftar obat (bisa impor).',
                        self::adaData(\Modules\Kesehatan\Models\Obat::class),
                        self::resourceUrl(\App\Filament\Resources\Kesehatan\ObatResource::class)),
                ],
            ],
            // 2 — PPDB
            'ppdb' => [
                'label' => 'PPDB',
                'icon' => 'heroicon-o-clipboard-document-list',
                'permission' => 'view_santri',
                'steps' => [
                    self::step('jadwal_ujian', 'Jadwal ujian', 'Jadwal ujian seleksi.',
                        self::adaData(\Modules\PPDB\Models\JadwalUjian::class),
                        self::resourceUrl(\App\Filament\Resources\PPDB\JadwalUjian\JadwalUjianResource::class)),
                    self::step('bank_soal', 'Bank/paket soal', 'Paket soal ujian masuk.',
                        self::adaData(\Modules\PPDB\Models\PaketSoal::class),
                        null),
                ],
            ],
            // 3 — AKADEMIK & TAHFIDZ (fold: tahfidz)
            'akademik' => [
                'label' => 'Akademik & Tahfidz',
                'icon' => 'heroicon-o-academic-cap',
                'permission' => 'manage_akademik',
                'steps' => [
                    self::step('lembaga', 'Lembaga / unit', 'Minimal satu lembaga (mis. Madrasah).',
                        self::adaData(\Modules\Akademik\Models\Lembaga::class),
                        self::resourceUrl(\App\Filament\Resources\Akademik\Lembaga\LembagaResource::class)),
                    self::step('periode', 'Periode akademik aktif', 'Tahun ajaran yang sedang berjalan.',
                        self::adaPeriodeAktif(),
                        self::resourceUrl(\App\Filament\Resources\Akademik\PeriodeAkademik\PeriodeAkademikResource::class)),
                    self::step('pelajaran', 'Pelajaran', 'Daftar mata pelajaran (bisa impor).',
                        self::adaData(\Modules\Akademik\Models\Pelajaran::class),
                        self::resourceUrl(\App\Filament\Resources\Akademik\Pelajaran\PelajaranResource::class)),
                    self::step('jadwal', 'Jadwal pelajaran', 'Jadwal per kelas (bisa impor).',
                        self::adaData(\Modules\Akademik\Models\JadwalPelajaran::class),
                        self::resourceUrl(\App\Filament\Resources\Akademik\JadwalPelajaran\JadwalPelajaranResource::class)),
                    self::step('halaqah', 'Halaqah tahfidz', 'Kelompok hafalan + musyrif (bisa impor).',
                        self::adaData(\Modules\Tahfidz\Models\Halaqah::class),
                        self::resourceUrl(\App\Filament\Resources\Tahfidz\Halaqah\HalaqahResource::class)),
                ],
            ],
            // 4 — KEUANGAN (operasional)
            'keuangan' => [
                'label' => 'Keuangan',
                'icon' => 'heroicon-o-banknotes',
                'permission' => 'manage_keuangan',
                'steps' => [
                    self::step('skema_biaya', 'Skema biaya', 'Template tagihan (SPP, dll).',
                        self::adaData(\Modules\Keuangan\Models\SkemaBiaya::class),
                        self::resourceUrl(\App\Filament\Resources\SkemaBiaya\SkemaBiayaResource::class)),
                    self::step('metode', 'Metode pembayaran', 'Tunai/transfer/QRIS, dll.',
                        self::adaData(\Modules\Keuangan\Models\MetodePembayaran::class),
                        null),
                    self::step('tagihan', 'Tagihan / SPP', 'Buat atau impor tagihan santri.',
                        self::adaData(\Modules\Keuangan\Models\Tagihan::class),
                        self::resourceUrl(\App\Filament\Resources\Tagihan\TagihanResource::class)),
                ],
            ],
            // 5 — AKUNTANSI & PAJAK (fondasi uang)
            'akuntansi' => [
                'label' => 'Akuntansi & Pajak',
                'icon' => 'heroicon-o-calculator',
                'permission' => 'manage_ledger',
                'steps' => [
                    self::step('coa', 'Bagan akun (COA)', 'Otomatis ter-seed; tinjau bila perlu.',
                        self::adaData(\Modules\Ledger\Models\Akun::class),
                        self::resourceUrl(\App\Filament\Resources\Ledger\AkunResource::class)),
                    self::step('sumber_dana', 'Sumber dana', 'Klasifikasi sumber dana.',
                        self::adaData(\Modules\Ledger\Models\SumberDana::class),
                        self::resourceUrl(\App\Filament\Resources\Ledger\SumberDanaResource::class)),
                    self::step('periode_akuntansi', 'Periode akuntansi', 'Periode buku berjalan.',
                        self::adaData(\Modules\Ledger\Models\PeriodeAkuntansi::class),
                        self::resourceUrl(\App\Filament\Resources\Ledger\PeriodeAkuntansiResource::class)),
                ],
            ],
            // 6 — KEPEGAWAIAN (fold: sdm)
            'kepegawaian' => [
                'label' => 'Kepegawaian',
                'icon' => 'heroicon-o-briefcase',
                'permission' => 'manage_payroll',
                'steps' => [
                    self::step('jabatan', 'Jabatan', 'Otomatis ter-seed; sesuaikan bila perlu.',
                        self::adaData(\Modules\Payroll\Models\Jabatan::class),
                        self::resourceUrl(\App\Filament\Resources\Payroll\Jabatan\JabatanResource::class)),
                    self::step('pegawai', 'Data pegawai', 'Guru & karyawan (bisa impor).',
                        self::adaData(\Modules\Payroll\Models\Employee::class),
                        self::resourceUrl(\App\Filament\Resources\Payroll\Employee\EmployeeResource::class)),
                ],
            ],
            // 7 — KELEMBAGAAN & KOMUNIKASI
            'kelembagaan' => [
                'label' => 'Kelembagaan & Komunikasi',
                'icon' => 'heroicon-o-building-office-2',
                'permission' => 'manage_tenant_settings',
                'steps' => [
                    self::step('website', 'Konten website', 'Artikel/profil pondok.',
                        self::adaData(\Modules\CMS\Models\Article::class),
                        null),
                    self::step('supplier', 'Supplier', 'Master pemasok (pengadaan).',
                        self::adaData(\Modules\Pengadaan\Models\Supplier::class),
                        null),
                    self::step('aset', 'Aset', 'Daftar aset/inventaris.',
                        self::adaData(\Modules\Pembiayaan\Models\Aset::class),
                        null),
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
                        self::pageUrl(\App\Filament\Pages\PengaturanBranding::class)),
                    self::step('integrasi', 'Integrasi API/WA', 'Kredensial WhatsApp/notifikasi.',
                        self::settingTerisi('wa_api_key'),
                        self::pageUrl(\App\Filament\Pages\IntegrasiApi::class)),
                    self::step('mutu', 'Sasaran mutu', 'Target/KPI mutu pesantren.',
                        self::adaData(\Modules\Mutu\Models\MutuSasaran::class),
                        null),
                ],
            ],
        ];
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
            $m = \Modules\Akademik\Models\PeriodeAkademik::class;

            return class_exists($m) && $m::query()->where('is_active', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function adaWali(): bool
    {
        try {
            return \App\Models\User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'wali'))
                ->exists();
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
