<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickAccessWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-access';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'groups' => array_filter([
                $this->group('Kesantrian', array_filter([
                    $this->link('Data Santri', 'heroicon-o-users', 'success', \App\Filament\Resources\Santri\SantriResource::class),
                    $this->link('Tambah Santri', 'heroicon-o-user-plus', 'success', \App\Filament\Resources\Santri\SantriResource::class, 'create'),
                    $this->link('Kelas', 'heroicon-o-academic-cap', 'success', \App\Filament\Resources\Kelas\KelasResource::class),
                    $this->link('Alumni', 'heroicon-o-user-group', 'success', \App\Filament\Resources\Alumni\AlumniResource::class),
                    $this->link('Izin Santri', 'heroicon-o-clipboard-document-check', 'success', \App\Filament\Resources\Kegiatan\IzinSantriResource::class),
                    $this->link('Kunjungan Wali', 'heroicon-o-home', 'success', \App\Filament\Resources\Kegiatan\KunjunganWaliResource::class),
                ])),
                $this->group('Keuangan', array_filter([
                    $this->link('Tagihan', 'heroicon-o-document-text', 'warning', \App\Filament\Resources\Tagihan\TagihanResource::class),
                    $this->link('Pembayaran', 'heroicon-o-banknotes', 'warning', \App\Filament\Resources\Pembayaran\PembayaranResource::class),
                    $this->link('Tabungan', 'heroicon-o-wallet', 'warning', \App\Filament\Resources\Tabungan\TabunganResource::class),
                    $this->link('Laporan', 'heroicon-o-chart-bar', 'warning', \App\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource::class),
                    $this->link('Jurnal', 'heroicon-o-book-open', 'warning', \App\Filament\Resources\Ledger\JurnalResource::class),
                    $this->link('Slip Gaji', 'heroicon-o-currency-dollar', 'warning', \App\Filament\Resources\Payroll\SlipGajiResource::class),
                    $this->link('Belanja', 'heroicon-o-shopping-cart', 'warning', \App\Filament\Resources\Pengadaan\BelanjaResource::class),
                ])),
                $this->group('Akademik', array_filter([
                    $this->link('Pelajaran', 'heroicon-o-book-open', 'info', \App\Filament\Resources\Akademik\Pelajaran\PelajaranResource::class),
                    $this->link('Jadwal', 'heroicon-o-clock', 'info', \App\Filament\Resources\Akademik\JadwalPelajaran\JadwalPelajaranResource::class),
                    $this->link('Nilai', 'heroicon-o-clipboard-document-list', 'info', \App\Filament\Resources\Akademik\Nilai\NilaiResource::class),
                    $this->link('Rapor', 'heroicon-o-document-chart-bar', 'info', \App\Filament\Resources\Akademik\Rapor\RaporResource::class),
                ])),
                $this->group('Asrama & Tahfidz', array_filter([
                    $this->link('Blok Asrama', 'heroicon-o-building-office', 'primary', \App\Filament\Resources\Asrama\BlokAsrama\BlokAsramaResource::class),
                    $this->link('Kamar', 'heroicon-o-home-modern', 'primary', \App\Filament\Resources\Asrama\KamarAsrama\KamarAsramaResource::class),
                    $this->link('Hafalan', 'heroicon-o-book-open', 'primary', \App\Filament\Resources\Tahfidz\HafalanTahfidzResource::class),
                    $this->link('Penilaian Tahfidz', 'heroicon-o-star', 'primary', \App\Filament\Resources\Tahfidz\PenilaianTahfidzResource::class),
                ])),
                $this->group('PPDB & Kegiatan', array_filter([
                    $this->link('Calon Santri', 'heroicon-o-user-plus', 'info', \App\Filament\Resources\PPDB\CalonSantriResource::class),
                    $this->link('Hasil Ujian', 'heroicon-o-check-badge', 'info', \App\Filament\Resources\PPDB\HasilUjianResource::class),
                    $this->link('Kegiatan', 'heroicon-o-calendar-days', 'info', \App\Filament\Resources\Kegiatan\KegiatanResource::class),
                ])),
                $this->group('Kesehatan & Media', array_filter([
                    $this->link('Riwayat Kesehatan', 'heroicon-o-heart', 'danger', \App\Filament\Resources\Kesehatan\RiwayatKesehatanResource::class),
                    $this->link('Konseling', 'heroicon-o-chat-bubble-left-right', 'danger', \App\Filament\Resources\Kesehatan\SesiKonselingResource::class),
                    $this->link('File Media', 'heroicon-o-photo', 'primary', \App\Filament\Resources\Media\MediaFileResource::class),
                ])),
                $this->group('Website & Pengaturan', array_filter([
                    $this->link('Artikel', 'heroicon-o-newspaper', 'gray', \App\Filament\Resources\CMS\Article\ArticleResource::class),
                    $this->link('Galeri', 'heroicon-o-camera', 'gray', \App\Filament\Resources\CMS\Gallery\GalleryResource::class),
                    $this->link('Broadcast WA', 'heroicon-o-megaphone', 'gray', \App\Filament\Resources\BroadcastWa\BroadcastWaResource::class),
                    $this->link('Pengguna', 'heroicon-o-cog-6-tooth', 'gray', \App\Filament\Resources\Users\UserResource::class),
                ])),
            ], fn ($g) => ! empty($g['items'])),
        ];
    }

    private function group(string $label, array $items): array
    {
        return ['label' => $label, 'items' => array_values($items)];
    }

    private function link(string $label, string $icon, string $color, string $resource, string $route = 'index'): ?array
    {
        try {
            if (! class_exists($resource)) {
                return null;
            }
            $url = $resource::getUrl($route);
        } catch (\Throwable) {
            return null;
        }

        return ['label' => $label, 'icon' => $icon, 'color' => $color, 'url' => $url];
    }
}
