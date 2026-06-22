<?php

namespace Modules\Mutu\Filament\Pages;

use App\Services\Mutu\MutuService;
use Filament\Pages\Page;
use Modules\Kesantrian\Models\Santri;

/**
 * 2a — Profil Mutu per santri: kartu ringkas + lampu kuning + tren hafalan
 * (tabel/angka sederhana — TANPA library chart).
 */
class ProfilMutuSantri extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Profil Mutu Santri';

    protected static ?string $title = 'Profil Mutu Santri';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan & Mutu';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'profil-mutu-santri';

    protected string $view = 'filament.pages.profil-mutu-santri';

    public ?int $santriId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getViewData(): array
    {
        $svc = app(MutuService::class);
        $santri = $this->santriId ? Santri::query()->find($this->santriId) : null;

        return [
            'santris' => Santri::query()->where('status', 'aktif')->orderBy('nama')->get(['id', 'nis', 'nama']),
            'santri' => $santri,
            'profil' => $santri ? $svc->profil($santri) : null,
            'lampu' => $santri ? $svc->lampu($santri) : [],
        ];
    }
}
