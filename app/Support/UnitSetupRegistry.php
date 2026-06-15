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
            'kesantrian' => [
                'label' => 'Kesantrian',
                'icon' => 'heroicon-o-user-group',
                'permission' => 'manage_santri',
                'steps' => [
                    self::step('identitas', 'Identitas pesantren', 'Nama, logo, kontak pondok.',
                        self::settingTerisi('branding_name'),
                        self::pageUrl(\App\Filament\Pages\PengaturanBranding::class)),
                    self::step('santri', 'Data santri', 'Impor dari Excel atau tambah manual.',
                        self::adaData(\Modules\Kesantrian\Models\Santri::class),
                        self::resourceUrl(\App\Filament\Resources\Santri\SantriResource::class)),
                    self::step('wali', 'Akun wali', 'Buat akun wali (login pakai no. HP).',
                        self::adaWali(),
                        self::resourceUrl(\App\Filament\Resources\Santri\SantriResource::class)),
                ],
            ],
            'akademik' => [
                'label' => 'Akademik',
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
                ],
            ],
            'keuangan' => [
                'label' => 'Keuangan',
                'icon' => 'heroicon-o-banknotes',
                'permission' => 'manage_keuangan',
                'steps' => [
                    self::step('skema_biaya', 'Skema biaya', 'Template tagihan (SPP, dll).',
                        self::adaData(\Modules\Keuangan\Models\SkemaBiaya::class),
                        self::resourceUrl(\App\Filament\Resources\SkemaBiaya\SkemaBiayaResource::class)),
                    self::step('tagihan', 'Tagihan / SPP', 'Buat atau impor tagihan santri.',
                        self::adaData(\Modules\Keuangan\Models\Tagihan::class),
                        self::resourceUrl(\App\Filament\Resources\Tagihan\TagihanResource::class)),
                    self::step('tabungan', 'Saldo tabungan', 'Saldo awal tabungan (bila go-live tengah tahun).',
                        self::adaData(\Modules\Keuangan\Models\Tabungan::class),
                        self::resourceUrl(\App\Filament\Resources\Tabungan\TabunganResource::class)),
                ],
            ],
            'asrama' => [
                'label' => 'Asrama',
                'icon' => 'heroicon-o-home-modern',
                'permission' => 'manage_santri',
                'steps' => [
                    self::step('blok', 'Blok & kamar', 'Bangunan & kamar (auto saat impor penempatan).',
                        self::adaData(\Modules\Asrama\Models\BlokAsrama::class),
                        self::resourceUrl(\App\Filament\Resources\Asrama\BlokAsrama\BlokAsramaResource::class)),
                    self::step('penempatan', 'Penempatan santri', 'Tempatkan santri ke kamar (bisa impor).',
                        self::adaData(\Modules\Asrama\Models\PenempatanAsrama::class),
                        self::resourceUrl(\App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource::class)),
                ],
            ],
            'sdm' => [
                'label' => 'SDM / Pegawai',
                'icon' => 'heroicon-o-briefcase',
                'permission' => 'manage_payroll',
                'steps' => [
                    self::step('pegawai', 'Data pegawai', 'Guru & karyawan (bisa impor).',
                        self::adaData(\Modules\Payroll\Models\Employee::class),
                        self::resourceUrl(\App\Filament\Resources\Payroll\Employee\EmployeeResource::class)),
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
