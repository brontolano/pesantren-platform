<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Akademik\Lembaga\LembagaResource;
use App\Filament\Resources\Payroll\Employee\EmployeeResource;
use App\Filament\Resources\Santri\SantriResource;
use Filament\Pages\Page;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Santri;
use Modules\Payroll\Models\Employee;

/**
 * WP-12 — Rekap "Kesiapan EMIS": kelengkapan data identitas yang dibutuhkan
 * pelaporan EMIS (NIK/NISN santri, NIK/NUPTK/sertifikasi pegawai, legalitas
 * lembaga). Hanya REKAP + tautan ke daftar yang kurang — ekspor terformat
 * sudah ada di halaman Ekspor EMIS; API EMIS sengaja TIDAK dibangun.
 */
class KesiapanEmis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Kesiapan EMIS';

    protected static ?string $title = 'Kesiapan Data EMIS';

    protected static string|\UnitEnum|null $navigationGroup = 'UKS & Alumni';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'kesiapan-emis';

    protected string $view = 'filament.pages.kesiapan-emis';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Angka rekap kesiapan EMIS — dihitung dengan query agregat (bukan kolom
     * tersimpan) agar selalu segar. Dipanggil juga dari test.
     *
     * @return array{
     *     santri: array{total: int, ber_nik: int, ber_nisn: int, persen_nik: float, persen_nisn: float},
     *     pegawai: array{total: int, ber_nik: int, ber_nuptk: int, ber_sertifikasi: int, persen_nik: float, persen_nuptk: float, persen_sertifikasi: float},
     *     lembaga: array{total: int, tanpa_legalitas: int},
     * }
     */
    public static function hitungStatistik(): array
    {
        $santriAktif = Santri::query()->where('status', 'aktif');
        $totalSantri = (clone $santriAktif)->count();
        $santriNik = (clone $santriAktif)->whereNotNull('nik')->where('nik', '!=', '')->count();
        $santriNisn = (clone $santriAktif)->whereNotNull('nisn')->where('nisn', '!=', '')->count();

        $pegawaiAktif = Employee::query()->where('aktif', true);
        $totalPegawai = (clone $pegawaiAktif)->count();
        $pegawaiNik = (clone $pegawaiAktif)->whereNotNull('nik')->where('nik', '!=', '')->count();
        $pegawaiNuptk = (clone $pegawaiAktif)->whereNotNull('nuptk')->where('nuptk', '!=', '')->count();
        $pegawaiSertifikasi = (clone $pegawaiAktif)->whereHas('sertifikasi')->count();

        $totalLembaga = Lembaga::query()->count();
        $lembagaTanpaLegalitas = Lembaga::query()->where(
            fn ($q) => $q
                ->whereNull('nomor_statistik')->orWhere('nomor_statistik', '')
                ->orWhereNull('izin_operasional')->orWhere('izin_operasional', ''),
        )->count();

        $persen = fn (int $isi, int $total): float => $total > 0 ? round($isi / $total * 100, 1) : 0.0;

        return [
            'santri' => [
                'total' => $totalSantri,
                'ber_nik' => $santriNik,
                'ber_nisn' => $santriNisn,
                'persen_nik' => $persen($santriNik, $totalSantri),
                'persen_nisn' => $persen($santriNisn, $totalSantri),
            ],
            'pegawai' => [
                'total' => $totalPegawai,
                'ber_nik' => $pegawaiNik,
                'ber_nuptk' => $pegawaiNuptk,
                'ber_sertifikasi' => $pegawaiSertifikasi,
                'persen_nik' => $persen($pegawaiNik, $totalPegawai),
                'persen_nuptk' => $persen($pegawaiNuptk, $totalPegawai),
                'persen_sertifikasi' => $persen($pegawaiSertifikasi, $totalPegawai),
            ],
            'lembaga' => [
                'total' => $totalLembaga,
                'tanpa_legalitas' => $lembagaTanpaLegalitas,
            ],
        ];
    }

    /**
     * Data untuk view: statistik + URL daftar-yang-kurang (resource + filter).
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $filterUrl = fn (string $resource, string $filter): string => $resource::getUrl('index', [
            'tableFilters' => [$filter => ['isActive' => true]],
        ]);

        return [
            'stat' => static::hitungStatistik(),
            // Link daftar pegawai hanya untuk yang boleh membuka EmployeeResource
            // (view_santri saja → link dead-end 403; sembunyikan).
            'bisaLihatPegawai' => EmployeeResource::canAccess(),
            'url' => [
                'santri_tanpa_nik' => $filterUrl(SantriResource::class, 'tanpa_nik'),
                'santri_tanpa_nisn' => $filterUrl(SantriResource::class, 'tanpa_nisn'),
                'pegawai_tanpa_nik' => $filterUrl(EmployeeResource::class, 'tanpa_nik'),
                'pegawai_tanpa_nuptk' => $filterUrl(EmployeeResource::class, 'tanpa_nuptk'),
                'pegawai_tanpa_sertifikasi' => $filterUrl(EmployeeResource::class, 'tanpa_sertifikasi'),
                'lembaga_tanpa_legalitas' => $filterUrl(LembagaResource::class, 'tanpa_legalitas'),
            ],
        ];
    }
}
