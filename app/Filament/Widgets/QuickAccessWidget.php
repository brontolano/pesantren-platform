<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SetupUnit;
use App\Filament\Resources\Akademik\JadwalPelajaran\JadwalPelajaranResource;
use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use App\Filament\Resources\Akademik\Pelajaran\PelajaranResource;
use App\Filament\Resources\Akademik\Rapor\RaporResource;
use App\Filament\Resources\Alumni\AlumniResource;
use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use App\Filament\Resources\BroadcastWa\BroadcastWaResource;
use App\Filament\Resources\CMS\Article\ArticleResource;
use App\Filament\Resources\CMS\Gallery\GalleryResource;
use Modules\Kegiatan\Filament\Resources\IzinSantriResource;
use Modules\Kegiatan\Filament\Resources\KegiatanResource;
use Modules\Kegiatan\Filament\Resources\KunjunganWaliResource;
use App\Filament\Resources\Kelas\KelasResource;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatanResource;
use Modules\Kesehatan\Filament\Resources\SesiKonselingResource;
use App\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource;
use App\Filament\Resources\Ledger\JurnalResource;
use App\Filament\Resources\Media\MediaFileResource;
use App\Filament\Resources\Payroll\SlipGajiResource;
use App\Filament\Resources\Pembayaran\PembayaranResource;
use App\Filament\Resources\Pengadaan\BelanjaResource;
use App\Filament\Resources\PPDB\CalonSantriResource;
use App\Filament\Resources\PPDB\HasilUjianResource;
use App\Filament\Resources\Santri\SantriResource;
use App\Filament\Resources\Tabungan\TabunganResource;
use App\Filament\Resources\Tagihan\TagihanResource;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
use Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource;
use App\Filament\Resources\Users\UserResource;
use App\Services\ModuleService;
use Filament\Widgets\Widget;

/**
 * "Akses Cepat" — grid ikon pintasan ke menu/aksi tersering di dasbor tenant.
 *
 * Tiap tile DIGERBANG modul + izin: hanya muncul bila modul aktif untuk tenant
 * DAN pengguna punya izinnya (super_admin lihat semua) — jadi grid otomatis
 * menyesuaikan tiap pesantren & peran, tak ada pintasan yang menabrak 403.
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
                $this->link('Kelas', 'heroicon-o-academic-cap', 'success', KelasResource::class, 'index', null, ['view_santri', 'manage_santri']),
                $this->link('Alumni', 'heroicon-o-user-group', 'success', AlumniResource::class, 'index', null, ['view_santri', 'manage_santri']),
                $this->link('Izin Santri', 'heroicon-o-clipboard-document-check', 'success', IzinSantriResource::class, 'index', 'kegiatan', ['manage_santri']),
                $this->link('Kunjungan Wali', 'heroicon-o-home', 'success', KunjunganWaliResource::class, 'index', 'kegiatan', ['view_santri']),
            ]),
            $this->group('Keuangan', [
                $this->link('Tagihan', 'heroicon-o-document-text', 'warning', TagihanResource::class, 'index', 'keuangan', ['view_keuangan', 'manage_keuangan']),
                $this->link('Pembayaran', 'heroicon-o-banknotes', 'warning', PembayaranResource::class, 'index', 'keuangan', ['view_keuangan', 'manage_keuangan']),
                $this->link('Tabungan', 'heroicon-o-wallet', 'warning', TabunganResource::class, 'index', 'keuangan', ['view_keuangan', 'manage_keuangan']),
                $this->link('Laporan', 'heroicon-o-chart-bar', 'warning', LaporanKeuanganResource::class, 'index', 'keuangan', ['view_keuangan', 'manage_keuangan']),
                $this->link('Jurnal', 'heroicon-o-book-open', 'warning', JurnalResource::class, 'index', 'ledger', ['view_ledger', 'manage_ledger']),
                $this->link('Slip Gaji', 'heroicon-o-currency-dollar', 'warning', SlipGajiResource::class, 'index', 'payroll', ['run_payroll']),
                $this->link('Belanja', 'heroicon-o-shopping-cart', 'warning', BelanjaResource::class, 'index', 'pengadaan', ['view_keuangan', 'input_belanja', 'manage_ledger']),
            ]),
            $this->group('Akademik', [
                $this->link('Pelajaran', 'heroicon-o-book-open', 'info', PelajaranResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
                $this->link('Jadwal', 'heroicon-o-clock', 'info', JadwalPelajaranResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
                $this->link('Nilai', 'heroicon-o-clipboard-document-list', 'info', NilaiResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
                $this->link('Rapor', 'heroicon-o-document-chart-bar', 'info', RaporResource::class, 'index', 'akademik', ['view_akademik', 'manage_akademik']),
            ]),
            $this->group('Asrama & Tahfidz', [
                $this->link('Blok Asrama', 'heroicon-o-building-office', 'primary', BlokAsramaResource::class, 'index', 'asrama', ['view_santri', 'manage_santri']),
                $this->link('Kamar', 'heroicon-o-home-modern', 'primary', KamarAsramaResource::class, 'index', 'asrama', ['view_santri', 'manage_santri']),
                $this->link('Hafalan', 'heroicon-o-book-open', 'primary', HafalanTahfidzResource::class, 'index', 'tahfidz', []),
                $this->link('Penilaian Tahfidz', 'heroicon-o-star', 'primary', PenilaianTahfidzResource::class, 'index', 'tahfidz', []),
            ]),
            $this->group('PPDB & Kegiatan', [
                $this->link('Calon Santri', 'heroicon-o-user-plus', 'info', CalonSantriResource::class, 'index', 'ppdb', ['view_santri', 'manage_santri']),
                $this->link('Hasil Ujian', 'heroicon-o-check-badge', 'info', HasilUjianResource::class, 'index', 'ppdb', ['view_santri', 'manage_santri']),
                $this->link('Kegiatan', 'heroicon-o-calendar-days', 'info', KegiatanResource::class, 'index', 'kegiatan', ['view_santri']),
            ]),
            $this->group('Kesehatan & Media', [
                $this->link('Riwayat Kesehatan', 'heroicon-o-heart', 'danger', RiwayatKesehatanResource::class, 'index', 'kesehatan', ['view_santri']),
                $this->link('Konseling', 'heroicon-o-chat-bubble-left-right', 'danger', SesiKonselingResource::class, 'index', 'kesehatan', ['view_santri']),
                $this->link('File Media', 'heroicon-o-photo', 'primary', MediaFileResource::class, 'index', 'file_storage', ['manage_tenant_settings']),
            ]),
            $this->group('Website & Pengaturan', [
                $this->link('Artikel', 'heroicon-o-newspaper', 'gray', ArticleResource::class, 'index', 'cms', ['manage_santri', 'manage_tenant_settings']),
                $this->link('Galeri', 'heroicon-o-camera', 'gray', GalleryResource::class, 'index', 'cms', ['manage_santri', 'manage_tenant_settings']),
                $this->link('Broadcast WA', 'heroicon-o-megaphone', 'gray', BroadcastWaResource::class, 'index', 'notifikasi', ['view_notifikasi', 'manage_notifikasi']),
                $this->link('Pusat Setup', 'heroicon-o-clipboard-document-check', 'gray', null, 'index', null, ['manage_santri', 'manage_akademik', 'manage_keuangan', 'manage_tenant_settings'], SetupUnit::class),
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

    /** Gerbang modul (super_admin lihat semua) — selaras trait ModuleGate. */
    private function moduleVisible(string $key): bool
    {
        try {
            if (auth()->user()?->hasRole('super_admin')) {
                return true;
            }
        } catch (\Throwable) {
            // tabel roles belum siap → lanjut gerbang normal
        }

        return app(ModuleService::class)
            ->isVisibleForTenant($key, tenancy()->initialized ? tenant() : null);
    }
}
