<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SetupUnit;
use App\Filament\Resources\Media\MediaFileResource;
use App\Filament\Resources\Santri\SantriResource;
use App\Filament\Resources\Users\UserResource;
use App\Services\ModuleService;
use Filament\Widgets\Widget;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use Modules\Akademik\Filament\Resources\Nilai\NilaiResource;
use Modules\Akademik\Filament\Resources\Pelajaran\PelajaranResource;
use Modules\Akademik\Filament\Resources\Rapor\RaporResource;
use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Modules\CMS\Filament\Resources\Article\ArticleResource;
use Modules\CMS\Filament\Resources\Gallery\GalleryResource;
use Modules\Kegiatan\Filament\Resources\IzinSantriResource;
use Modules\Kegiatan\Filament\Resources\KegiatanResource;
use Modules\Kegiatan\Filament\Resources\KunjunganWaliResource;
use Modules\Kesantrian\Filament\Resources\Alumni\AlumniResource;
use Modules\Kesantrian\Filament\Resources\Kelas\KelasResource;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatanResource;
use Modules\Kesehatan\Filament\Resources\SesiKonselingResource;
use Modules\Keuangan\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource;
use Modules\Keuangan\Filament\Resources\Pembayaran\PembayaranResource;
use Modules\Keuangan\Filament\Resources\Tabungan\TabunganResource;
use Modules\Keuangan\Filament\Resources\Tagihan\TagihanResource;
use Modules\Ledger\Filament\Resources\JurnalResource;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\BroadcastWaResource;
use Modules\Payroll\Filament\Resources\SlipGajiResource;
use Modules\Pengadaan\Filament\Resources\BelanjaResource;
use Modules\PPDB\Filament\Resources\CalonSantriResource;
use Modules\PPDB\Filament\Resources\HasilUjianResource;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
use Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource;

/**
 * "Akses Cepat" — grid ikon pintasan ke menu/aksi tersering di dasbor tenant.
 *
 * Tiap tile DIGERBANG modul + izin: hanya muncul bila modul aktif untuk tenant
 * DAN pengguna punya izinnya (pemilik PLATFORM lihat semua; super_admin
 * PESANTREN ikut gerbang modul tenantnya) — grid menyesuaikan tiap pesantren &
 * peran, tak ada pintasan yang menabrak 403.
 * Grup yang kosong otomatis hilang; widget tak tampil bila tak ada satu pun tile.
 */
class QuickAccessWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-access';

    protected static ?int $sort = -7; // prominen, di bawah greeting/onboarding/stats

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        try {
            return collect((new static)->buildGroups())->isNotEmpty();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function getViewData(): array
    {
        return ['groups' => $this->buildGroups()];
    }

    /** @return array<int, array{label:string, items:array<int,array<string,string>>}> */
    protected function buildGroups(): array
    {
        return array_values(array_filter([
            $this->group('Kesantrian', [
                $this->link('Data Santri', 'heroicon-o-users', 'success', SantriResource::class, 'index', null, ['view_santri', 'manage_santri']),
                $this->link('Tambah Santri', 'heroicon-o-user-plus', 'success', SantriResource::class, 'create', null, ['manage_santri']),
                $this->link('Kelas', 'heroicon-o-academic-cap', 'success', KelasResource::class, 'index', null, ['view_akademik', 'manage_akademik', 'manage_santri']),
                $this->link('Alumni', 'heroicon-o-user-group', 'success', AlumniResource::class, 'index', null, ['view_alumni', 'manage_alumni']),
                $this->link('Izin Santri', 'heroicon-o-clipboard-document-check', 'success', IzinSantriResource::class, 'index', 'kegiatan', ['manage_kegiatan']),
                $this->link('Kunjungan Wali', 'heroicon-o-home', 'success', KunjunganWaliResource::class, 'index', 'kegiatan', ['view_kegiatan']),
            ]),
            $this->group('Keuangan', [
                $this->link('Tagihan', 'heroicon-o-document-text', 'warning', TagihanResource::class, 'index', 'keuangan', ['view_tagihan', 'manage_tagihan']),
                $this->link('Pembayaran', 'heroicon-o-banknotes', 'warning', PembayaranResource::class, 'index', 'keuangan', ['view_tagihan', 'manage_tagihan']),
                $this->link('Tabungan', 'heroicon-o-wallet', 'warning', TabunganResource::class, 'index', 'keuangan', ['view_tabungan', 'manage_tabungan']),
                $this->link('Laporan', 'heroicon-o-chart-bar', 'warning', LaporanKeuanganResource::class, 'index', 'keuangan', ['view_laporan_keuangan']),
                $this->link('Jurnal', 'heroicon-o-book-open', 'warning', JurnalResource::class, 'index', 'ledger', ['view_ledger', 'manage_ledger']),
                $this->link('Slip Gaji', 'heroicon-o-currency-dollar', 'warning', SlipGajiResource::class, 'index', 'payroll', ['run_payroll']),
                $this->link('Belanja', 'heroicon-o-shopping-cart', 'warning', BelanjaResource::class, 'index', 'pengadaan', ['input_belanja', 'manage_ledger']),
            ]),
            $this->group('Akademik', [
                $this->link('Pelajaran', 'heroicon-o-book-open', 'info', PelajaranResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
                $this->link('Jadwal', 'heroicon-o-clock', 'info', JadwalPelajaranResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
                $this->link('Nilai', 'heroicon-o-clipboard-document-list', 'info', NilaiResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
                $this->link('Rapor', 'heroicon-o-document-chart-bar', 'info', RaporResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
            ]),
            $this->group('Asrama & Tahfidz', [
                $this->link('Blok Asrama', 'heroicon-o-building-office', 'primary', BlokAsramaResource::class, 'index', 'asrama', ['view_asrama', 'manage_asrama']),
                $this->link('Kamar', 'heroicon-o-home-modern', 'primary', KamarAsramaResource::class, 'index', 'asrama', ['view_asrama', 'manage_asrama']),
                $this->link('Hafalan', 'heroicon-o-book-open', 'primary', HafalanTahfidzResource::class, 'index', 'tahfidz', ['view_tahfidz', 'manage_tahfidz']),
                $this->link('Penilaian Tahfidz', 'heroicon-o-star', 'primary', PenilaianTahfidzResource::class, 'index', 'tahfidz', ['view_tahfidz', 'manage_tahfidz']),
            ]),
            $this->group('PPDB & Kegiatan', [
                $this->link('Calon Santri', 'heroicon-o-user-plus', 'info', CalonSantriResource::class, 'index', 'ppdb', ['view_ppdb', 'manage_ppdb']),
                $this->link('Hasil Ujian', 'heroicon-o-check-badge', 'info', HasilUjianResource::class, 'index', 'ppdb', ['view_ppdb', 'manage_ppdb']),
                $this->link('Kegiatan', 'heroicon-o-calendar-days', 'info', KegiatanResource::class, 'index', 'kegiatan', ['view_kegiatan']),
            ]),
            $this->group('Kesehatan & Media', [
                $this->link('Riwayat Kesehatan', 'heroicon-o-heart', 'danger', RiwayatKesehatanResource::class, 'index', 'kesehatan', ['view_kesehatan']),
                $this->link('Konseling', 'heroicon-o-chat-bubble-left-right', 'danger', SesiKonselingResource::class, 'index', 'kesehatan', ['view_konseling']),
                $this->link('File Media', 'heroicon-o-photo', 'primary', MediaFileResource::class, 'index', 'file_storage', ['manage_tenant_settings']),
            ]),
            $this->group('Website & Pengaturan', [
                $this->link('Artikel', 'heroicon-o-newspaper', 'gray', ArticleResource::class, 'index', 'cms', ['manage_santri', 'manage_tenant_settings']),
                $this->link('Galeri', 'heroicon-o-camera', 'gray', GalleryResource::class, 'index', 'cms', ['manage_santri', 'manage_tenant_settings']),
                $this->link('Broadcast WA', 'heroicon-o-megaphone', 'gray', BroadcastWaResource::class, 'index', 'notifikasi', ['manage_notifikasi']),
                $this->link('Pusat Setup', 'heroicon-o-clipboard-document-check', 'gray', null, 'index', null, ['manage_tenant_settings'], SetupUnit::class),
                $this->link('Pengguna', 'heroicon-o-cog-6-tooth', 'gray', UserResource::class, 'index', null, ['manage_users']),
            ]),
        ], fn (array $g): bool => $g['items'] !== []));
    }

    /**
     * @param  array<int, array<string,string>|null>  $items
     * @return array{label:string, items:array<int,array<string,string>>}
     */
    private function group(string $label, array $items): array
    {
        return ['label' => $label, 'items' => array_values(array_filter($items))];
    }

    /**
     * Bangun tile pintasan — null (disembunyikan) bila kelas tak ada, modul mati,
     * atau pengguna tak punya izin. Bila $page diisi, tautan ke Page (bukan Resource).
     *
     * @param  list<string>  $permission
     * @return array{label:string, icon:string, color:string, url:string}|null
     */
    private function link(string $label, string $icon, string $color, ?string $resource, string $route = 'index', ?string $module = null, array $permission = [], ?string $page = null): ?array
    {
        $target = $page ?? $resource;
        if ($target === null || ! class_exists($target)) {
            return null;
        }
        if ($module !== null && ! $this->moduleVisible($module)) {
            return null;
        }
        if ($permission !== [] && ! (auth()->user()?->canAny($permission) ?? false)) {
            return null;
        }

        try {
            $url = $page !== null ? $page::getUrl() : $resource::getUrl($route);
        } catch (\Throwable) {
            return null;
        }

        return ['label' => $label, 'icon' => $icon, 'color' => $color, 'url' => $url];
    }

    /**
     * Gerbang modul — SELARAS trait App\Filament\Concerns\ModuleGate.
     * Bypass HANYA pemilik PLATFORM (super_admin tanpa tenant). super_admin
     * PESANTREN ikut gerbang modul tenantnya, agar tile pintasan tak pernah
     * menabrak 403 ke modul yang nonaktif untuk tenant tsb.
     */
    private function moduleVisible(string $key): bool
    {
        try {
            if (auth()->user()?->isPlatformSuperAdmin()) {
                return true;
            }
        } catch (\Throwable) {
            // tabel roles belum siap → lanjut gerbang normal
        }

        return app(ModuleService::class)
            ->isVisibleForTenant($key, tenancy()->initialized ? tenant() : null);
    }
}
