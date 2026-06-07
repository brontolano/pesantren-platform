<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * "Akses Cepat / Quick Access" (mockup): grid ikon pintasan ke resource yang ADA.
 * Pintasan untuk modul yang belum ada (Surat, Pengajuan) ditandai "Segera" &
 * non-aktif — jujur, tidak menyesatkan. URL di-resolve aman (try/catch).
 */
class QuickAccessWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-access';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'shortcuts' => array_values(array_filter([
                $this->link('Tambah Santri', 'heroicon-o-user-plus', 'success', \App\Filament\Resources\Santri\SantriResource::class, 'create'),
                $this->link('Pembayaran', 'heroicon-o-banknotes', 'success', \App\Filament\Resources\Pembayaran\PembayaranResource::class),
                $this->link('Tagihan', 'heroicon-o-document-text', 'warning', \App\Filament\Resources\Tagihan\TagihanResource::class),
                $this->link('Buat Agenda', 'heroicon-o-calendar-days', 'info', \App\Filament\Resources\Kegiatan\KegiatanResource::class, 'create'),
                $this->link('Laporan', 'heroicon-o-chart-bar', 'primary', \App\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource::class),
                $this->link('Data Santri', 'heroicon-o-users', 'info', \App\Filament\Resources\Santri\SantriResource::class),
                $this->soon('Buat Surat', 'heroicon-o-envelope'),
                $this->soon('Pengajuan', 'heroicon-o-inbox-arrow-down'),
            ])),
        ];
    }

    /** Pintasan aktif ke sebuah resource (null bila resource/route tak tersedia). */
    private function link(string $label, string $icon, string $color, string $resource, string $route = 'index'): ?array
    {
        try {
            if (! class_exists($resource)) {
                return null;
            }
            $url = $resource::getUrl($route);
        } catch (\Throwable $e) {
            return null;
        }

        return ['label' => $label, 'icon' => $icon, 'color' => $color, 'url' => $url, 'enabled' => true];
    }

    /** Pintasan modul yang belum ada → ditandai "Segera", non-aktif. */
    private function soon(string $label, string $icon): array
    {
        return ['label' => $label, 'icon' => $icon, 'color' => 'gray', 'url' => null, 'enabled' => false];
    }
}
