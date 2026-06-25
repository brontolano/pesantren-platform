<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\PusatModul;
use App\Filament\Pages\SetupPesantren;
use App\Filament\Pages\SetupUnit;
use App\Filament\Resources\Santri\SantriResource;
use App\Models\Setting;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Filament\Resources\Article\ArticleResource;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;
use Modules\Kesantrian\Models\Santri;

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
                'label' => 'Selesaikan Setup Pesantren',
                'desc' => 'Atur fondasi pondok: identitas, lembaga, periode, & kebijakan (wizard terpandu).',
                'icon' => 'heroicon-o-sparkles',
                'done' => self::setupSelesai(),
                'url' => self::pageUrl(SetupPesantren::class),
            ],
            [
                'label' => 'Pilih & pasang fitur pesantren',
                'desc' => 'Aktifkan modul yang Anda butuhkan di Pusat Modul, lalu lengkapi datanya.',
                'icon' => 'heroicon-o-squares-plus',
                'done' => self::adaFiturDipasang(),
                'url' => self::pageUrl(PusatModul::class),
            ],
            [
                'label' => 'Masukkan data santri',
                'desc' => 'Impor dari Excel atau tambah satu per satu.',
                'icon' => 'heroicon-o-user-group',
                'done' => self::adaData(Santri::class),
                'url' => self::urlOf(SantriResource::class, 'create'),
            ],
            [
                'label' => 'Tautkan akun wali',
                'desc' => 'Agar wali bisa pantau tagihan & tabungan lewat HP.',
                'icon' => 'heroicon-o-users',
                'done' => self::adaWali(),
                'url' => self::urlOf(SantriResource::class),
            ],
            [
                'label' => 'Tampilkan website pondok',
                'desc' => 'Tulis artikel atau unggah foto galeri.',
                'icon' => 'heroicon-o-globe-alt',
                'done' => self::adaKontenWebsite(),
                'url' => self::urlOf(ArticleResource::class, 'create'),
            ],
        ];
    }

    private static function setupSelesai(): bool
    {
        try {
            return Setting::getTenantValue('setup.selesai') !== null;
        } catch (\Throwable) {
            return false;
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

    private static function adaData(string $model): bool
    {
        try {
            return class_exists($model) && $model::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Sudah memasang minimal satu fitur (modul released aktif) lewat Pusat Modul.
     *  Baca tabel CENTRAL tenant_modules secara eksplisit (selaras ModuleService). */
    private static function adaFiturDipasang(): bool
    {
        try {
            $conn = config('tenancy.database.central_connection', config('database.default'));

            return DB::connection($conn)
                ->table('tenant_modules')
                ->where('tenant_id', tenant('id'))
                ->where('is_active', true)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function adaWali(): bool
    {
        try {
            return User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'wali'))
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function adaKontenWebsite(): bool
    {
        try {
            $artikel = class_exists(Article::class)
                && Article::query()->where('is_published', true)->exists();
            $galeri = class_exists(Gallery::class)
                && Gallery::query()->exists();

            return $artikel || $galeri;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function pusatSetupUrl(): ?string
    {
        try {
            return class_exists(SetupUnit::class)
                ? SetupUnit::getUrl()
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
