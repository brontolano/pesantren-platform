<?php

namespace Modules\Kegiatan\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use InvalidArgumentException;
use Modules\Kegiatan\Models\PengajuanJenguk;
use Modules\Kegiatan\Services\PenjengukanService;

/**
 * WP-30 — Pengajuan Jenguk: antrean konfirmasi manual (status diajukan)
 * untuk pengurus. Aksi Konfirmasi/Tolak memanggil PenjengukanService::putuskan.
 * Grup Kesantrian. Didaftar EKSPLISIT di TenantPanelProvider.
 */
class PengajuanJengukPanel extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 61;

    protected static ?string $slug = 'pengajuan-jenguk';

    protected string $view = 'filament.pages.pengajuan-jenguk';

    /** @var array<int, array<string, mixed>> */
    public array $pengajuan = [];

    public static function getNavigationLabel(): string
    {
        return 'Pengajuan Jenguk';
    }

    public function getTitle(): string
    {
        return 'Pengajuan Jenguk';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->muatPengajuan();
    }

    public function muatPengajuan(): void
    {
        $this->pengajuan = PengajuanJenguk::query()
            ->with(['santri:id,nis,nama', 'pengaju:id,name'])
            ->where('status', 'diajukan')
            ->orderBy('tanggal')
            ->limit(200)
            ->get()
            ->map(fn (PengajuanJenguk $p): array => [
                'id' => $p->id,
                'santri' => $p->santri?->nama ?? '-',
                'nis' => (string) ($p->santri?->nis ?? ''),
                'pengaju' => $p->pengaju?->name ?? '-',
                'tanggal' => $p->tanggal->format('d-m-Y'),
                'catatan' => $p->catatan,
            ])->all();
    }

    public function putuskan(int $id, string $keputusan): void
    {
        abort_unless(static::canAccess(), 403);

        $pengajuan = PengajuanJenguk::findOrFail($id);

        try {
            app(PenjengukanService::class)->putuskan($pengajuan, $keputusan);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->muatPengajuan();

            return;
        }

        Notification::make()
            ->title($keputusan === 'dikonfirmasi' ? 'Pengajuan dikonfirmasi.' : 'Pengajuan ditolak.')
            ->success()
            ->send();

        $this->muatPengajuan();
    }
}
