<?php

namespace Modules\AI\Filament\Pages;

use App\Services\ModuleService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\AI\Models\AiApproval;
use Modules\AI\Services\Settings\RingkasanKebijakan;
use Modules\AI\Services\Settings\TenantSettingService;

/**
 * T-A011 — halaman persetujuan Kyai/Mudir atas pengajuan pengaturan AI.
 *
 * Menampilkan Ringkasan Kebijakan versi yang diajukan (template yang SAMA
 * dengan langkah akhir Wizard) lalu tombol Setujui/Tolak (+alasan).
 * Identitas dan waktu keputusan dicatat oleh TenantSettingService::putuskan().
 */
class PersetujuanAi extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim Digital';

    protected static ?string $navigationLabel = 'Persetujuan Pimpinan';

    protected static ?string $title = 'Persetujuan Pimpinan — Kebijakan Asisten AI';

    protected static ?string $slug = 'persetujuan-ai';

    protected static ?int $navigationSort = 2;

    protected string $view = 'ai::filament.pages.persetujuan-ai';

    /** Alasan keputusan per approval id (diisi dari textarea blade). */
    public array $alasan = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        return $modulTampil && (Auth::user()?->can('ai.setting.setujui') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Daftar pengajuan yang menunggu keputusan (dipakai blade). */
    public function approvalMenunggu(): Collection
    {
        return AiApproval::query()
            ->with('setting')
            ->where('status', AiApproval::STATUS_MENUNGGU)
            ->orderBy('created_at')
            ->get();
    }

    /** Ringkasan Kebijakan untuk satu pengajuan (template sama dgn Wizard). */
    public function ringkasanUntuk(AiApproval $approval): array
    {
        return app(RingkasanKebijakan::class)->susun((array) $approval->setting?->payload);
    }

    public function setujui(int $approvalId): void
    {
        $this->putuskan($approvalId, true);
    }

    public function tolak(int $approvalId): void
    {
        $this->putuskan($approvalId, false);
    }

    protected function putuskan(int $approvalId, bool $setuju): void
    {
        try {
            app(TenantSettingService::class)->putuskan(
                $approvalId,
                Auth::user(),
                $setuju,
                trim((string) ($this->alasan[$approvalId] ?? '')) ?: null,
            );

            unset($this->alasan[$approvalId]);

            Notification::make()
                ->title($setuju ? 'Pengajuan disetujui' : 'Pengajuan ditolak')
                ->body($setuju
                    ? 'Kebijakan baru kini berlaku. Jazakumullahu khairan atas perhatiannya.'
                    : 'Pengajuan dikembalikan menjadi draf agar dapat diperbaiki oleh pengelola.')
                ->success()
                ->send();
        } catch (AuthorizationException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Keputusan belum tercatat')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
