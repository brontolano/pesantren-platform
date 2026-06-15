<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use Filament\Widgets\Widget;

/**
 * Checklist Onboarding — memandu pengurus baru menyelesaikan langkah pertama
 * MENGIKUTI JALUR ADOPSI NYATA (profil → santri → wali → website). Keuangan
 * SENGAJA tidak di sini (bukan jalur adopsi awal). Otomatis hilang saat semua
 * langkah selesai atau ditutup pengguna. Baca state nyata DB; aman (try/catch).
 */
class OnboardingChecklist extends Widget
{
    protected string $view = 'filament.widgets.onboarding-checklist';

    protected static ?int $sort = -9; // paling atas dashboard

    protected int|string|array $columnSpan = 'full';

    private const SETTING_KEY = 'onboarding_checklist_dismissed';

    public static function canView(): bool
    {
        try {
            if ((bool) Setting::getTenantValue(self::SETTING_KEY, false)) {
                return false; // sudah ditutup pengguna
            }

            return collect(static::buildSteps())->contains(fn (array $s): bool => ! $s['done']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function dismiss(): void
    {
        try {
            Setting::query()->updateOrCreate(
                ['tenant_id' => tenant('id'), 'key' => self::SETTING_KEY],
                ['value' => true],
            );
        } catch (\Throwable) {
            // diam — menutup checklist tak boleh menggagalkan dashboard
        }

        $this->dispatch('$refresh');
    }

    protected function getViewData(): array
    {
        $steps = static::buildSteps();
        $selesai = collect($steps)->where('done', true)->count();

        return [
            'steps' => $steps,
            'selesai' => $selesai,
            'total' => count($steps),
            'persen' => count($steps) > 0 ? (int) round($selesai / count($steps) * 100) : 0,
            'pusatSetupUrl' => self::pusatSetupUrl(),
        ];
    }

    /** @return array<int, array{label:string,desc:string,icon:string,done:bool,url:?string}> */
    protected static function buildSteps(): array
    {
        return [
            [
                'label' => 'Lengkapi profil pondok',
                'desc' => 'Isi nama, logo, dan kontak pesantren.',
                'icon' => 'heroicon-o-building-library',
                'done' => self::profilTerisi(),
                'url' => self::urlOf(\App\Filament\Resources\CMS\Profile\ProfileResource::class),
            ],
            [
                'label' => 'Masukkan data santri',
                'desc' => 'Impor dari Excel atau tambah satu per satu.',
                'icon' => 'heroicon-o-user-group',
                'done' => self::adaData(\Modules\Kesantrian\Models\Santri::class),
                'url' => self::urlOf(\App\Filament\Resources\Santri\SantriResource::class, 'create'),
            ],
            [
                'label' => 'Tautkan akun wali',
                'desc' => 'Agar wali bisa pantau tagihan & tabungan lewat HP.',
                'icon' => 'heroicon-o-users',
                'done' => self::adaWali(),
                'url' => self::urlOf(\App\Filament\Resources\Santri\SantriResource::class),
            ],
            [
                'label' => 'Tampilkan website pondok',
                'desc' => 'Tulis artikel atau unggah foto galeri.',
                'icon' => 'heroicon-o-globe-alt',
                'done' => self::adaKontenWebsite(),
                'url' => self::urlOf(\App\Filament\Resources\CMS\Article\ArticleResource::class, 'create'),
            ],
        ];
    }

    private static function profilTerisi(): bool
    {
        try {
            $nama = Setting::getTenantStringValue('branding_name');

            return is_string($nama) && trim($nama) !== '';
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

    private static function adaKontenWebsite(): bool
    {
        try {
            $artikel = class_exists(\Modules\CMS\Models\Article::class)
                && \Modules\CMS\Models\Article::query()->where('is_published', true)->exists();
            $galeri = class_exists(\Modules\CMS\Models\Gallery::class)
                && \Modules\CMS\Models\Gallery::query()->exists();

            return $artikel || $galeri;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function pusatSetupUrl(): ?string
    {
        try {
            return class_exists(\App\Filament\Pages\SetupUnit::class)
                ? \App\Filament\Pages\SetupUnit::getUrl()
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function urlOf(string $resource, string $route = 'index'): ?string
    {
        try {
            return class_exists($resource) ? $resource::getUrl($route) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
