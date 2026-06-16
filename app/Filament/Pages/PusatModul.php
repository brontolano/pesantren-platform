<?php

namespace App\Filament\Pages;

use App\Models\ModuleRegistry;
use App\Models\TenantModule;
use App\Services\ModuleService;
use App\Support\ModuleRecommendationRegistry;
use App\Support\ModuleSetupMap;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Pusat Modul — "toko fitur" self-installer (panel TENANT).
 *
 * Super admin pesantren MEMASANG fitur (modul released) satu per satu, dipandu
 * wizard yang sudah ada (SetupUnit). Navigasi hanya menampilkan modul yang SIAP;
 * di sinilah modul "tersedia / perlu disiapkan" hidup sebelum muncul di menu.
 *
 * Pemisahan wewenang (resolusi blocker platform-only): GRANT/izin paket tetap di
 * panel platform (AktivasiModulTenant); PASANG/aktif ada di sini, digerbang
 * permission manage_modules + isTenantStaff, hanya menulis tenant sendiri.
 *
 * JEBAKAN: panel tenant = koneksi default tenant, tetapi tenant_modules CENTRAL →
 * SEMUA tulis dibungkus tenancy()->central().
 */
class PusatModul extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationLabel = 'Pusat Modul';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = -10;

    protected static ?string $title = 'Pusat Modul — Pilih Fitur Pesantren';

    protected string $view = 'filament.pages.pusat-modul';

    /** Penjelasan awam tiap fitur (1 kalimat). */
    private const DESKRIPSI = [
        'ppdb' => 'Penerimaan santri baru: formulir pendaftaran online, seleksi, & pengumuman.',
        'portal_wali' => 'Wali pantau tagihan, tabungan, & perkembangan anak lewat HP.',
        'akademik' => 'Lembaga, kelas, mata pelajaran, jadwal, nilai, & rapor.',
        'asrama' => 'Blok/kamar, penempatan santri, & presensi asrama.',
        'tahfidz' => 'Halaqah, setoran hafalan, & munaqasyah.',
        'kesehatan' => 'UKS: stok obat, rekam pemeriksaan, & konseling.',
        'kegiatan' => 'Agenda kegiatan & perizinan santri.',
        'mutu' => 'Sasaran & rapor mutu pesantren (SYAMIL).',
        'ledger' => 'Buku besar akuntansi (double-entry) untuk laporan keuangan.',
        'pajak' => 'Perhitungan & laporan pajak.',
        'payroll' => 'Gaji/bisyaroh pegawai & guru.',
        'pembiayaan' => 'Aset & inventaris pesantren.',
        'pengadaan' => 'Pembelian, stok, & supplier.',
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && $user !== null
            && $user->isTenantStaff()              // hanya staf tenant (defense-in-depth)
            && $user->can('manage_modules');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getViewData(): array
    {
        $svc = app(ModuleService::class);
        $tenant = tenancy()->initialized ? tenant() : null;
        $rekomendasi = ModuleRecommendationRegistry::untukTenantSaatIni();

        $inti = [];
        $aktif = [];
        $perluDisiapkan = [];
        $disarankan = [];
        $lainnya = [];

        foreach (ModuleRegistry::query()->orderBy('sort')->get() as $m) {
            if ($m->is_core) {
                $inti[] = ['key' => $m->key, 'label' => $m->label];

                continue;
            }

            $status = $svc->moduleStatus($m->key, $tenant);

            // Modul development utk tenant non-dev (locked) → jangan tampilkan (hindari bingung).
            if ($status === ModuleService::STATUS_LOCKED) {
                continue;
            }

            $kartu = [
                'key' => $m->key,
                'label' => $m->label,
                'deskripsi' => self::DESKRIPSI[$m->key] ?? '',
                'unit' => ModuleSetupMap::unit($m->key),
                'setupUrl' => $this->setupUrl($m->key),
                'progress' => $this->langkahProgress($m->key),
                'prasyarat' => $this->labelPrasyarat($m->key),
                'direkomendasikan' => in_array($m->key, $rekomendasi, true),
            ];

            match ($status) {
                ModuleService::STATUS_READY => $aktif[] = $kartu,
                ModuleService::STATUS_INSTALLED => $perluDisiapkan[] = $kartu,
                default => $kartu['direkomendasikan'] ? $disarankan[] = $kartu : $lainnya[] = $kartu,
            };
        }

        return [
            'inti' => $inti,
            'aktif' => $aktif,
            'perluDisiapkan' => $perluDisiapkan,
            'disarankan' => $disarankan,
            'lainnya' => $lainnya,
        ];
    }

    /** Tombol header: pasang sekaligus semua modul yang disarankan (+ prasyaratnya). */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pasangDisarankan')
                ->label('Pasang Semua yang Disarankan')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn (): bool => $this->adaDisarankanTersedia())
                ->requiresConfirmation()
                ->modalHeading('Pasang fitur yang disarankan')
                ->modalDescription('Kami akan memasang fitur yang cocok untuk pesantren Anda beserta prasyaratnya. Anda tetap perlu melengkapi datanya agar siap dipakai.')
                ->modalSubmitActionLabel('Ya, pasang semua')
                ->action(fn () => $this->pasangDisarankan()),
        ];
    }

    /** Pasang satu fitur (dipanggil dari kartu via wire:click + wire:confirm). */
    public function pasang(string $module): void
    {
        $this->pastikanBoleh();

        $dipasang = $this->installModules([$module]);

        if ($dipasang === []) {
            Notification::make()->title('Fitur sudah terpasang.')->info()->send();

            return;
        }

        // Bila modul utama masih perlu langkah setup → antar ke wizard.
        if (! ModuleSetupMap::ready($module) && ($url = $this->setupUrl($module)) !== null) {
            Notification::make()
                ->title('Fitur dipasang 🎉')
                ->body('Sekarang lengkapi datanya supaya siap dipakai.')
                ->success()
                ->send();

            $this->redirect($url);

            return;
        }

        Notification::make()
            ->title('Fitur dipasang & siap dipakai 🎉')
            ->body('Menunya kini muncul di navigasi.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    /** Pasang semua modul yang disarankan & belum terpasang. */
    public function pasangDisarankan(): void
    {
        $this->pastikanBoleh();

        $svc = app(ModuleService::class);
        $tenant = tenancy()->initialized ? tenant() : null;

        $target = array_values(array_filter(
            ModuleRecommendationRegistry::untukTenantSaatIni(),
            fn (string $k): bool => $svc->moduleStatus($k, $tenant) === ModuleService::STATUS_AVAILABLE,
        ));

        $dipasang = $this->installModules($target);

        Notification::make()
            ->title(count($dipasang).' fitur dipasang')
            ->body('Lengkapi data tiap fitur agar siap dipakai (lihat kartu "Perlu dilengkapi").')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    /** Nonaktifkan fitur (reversible — data TIDAK dihapus). */
    public function nonaktif(string $module): void
    {
        $this->pastikanBoleh();

        $svc = app(ModuleService::class);
        if ($svc->isCore($module)) {
            Notification::make()->title('Modul inti tak bisa dimatikan.')->warning()->send();

            return;
        }

        $tid = tenant('id');
        tenancy()->central(function () use ($tid, $module): void {
            $row = TenantModule::query()
                ->where('tenant_id', $tid)
                ->where('module_name', $module)
                ->first();
            if ($row !== null) {
                $row->forceFill(['is_active' => false])->save();
            }
        });

        app()->forgetInstance('__active_modules_'.$tid);

        Notification::make()
            ->title('Fitur dinonaktifkan')
            ->body('Menunya disembunyikan. Datanya tetap aman — bisa diaktifkan lagi kapan saja.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    /**
     * Tulis aktivasi modul + prasyaratnya (resolusi topologis) ke CENTRAL.
     * Mengembalikan daftar key yang BARU dipasang (yang sebelumnya tak aktif).
     *
     * @param  list<string>  $modules
     * @return list<string>
     */
    private function installModules(array $modules): array
    {
        $svc = app(ModuleService::class);
        $tenant = tenancy()->initialized ? tenant() : null;

        // Susun urutan: prasyarat dulu, lalu modul (anti-siklus).
        $urut = [];
        foreach ($modules as $m) {
            $this->resolveDeps($m, $urut);
        }

        // Hitung kesiapan instan SEBELUM masuk central() (UnitSetupRegistry baca DB tenant).
        $instan = [];
        $baru = [];
        foreach ($urut as $k) {
            if ($svc->isCore($k) || ! $svc->isRegistered($k)) {
                continue; // inti/tak terdaftar tak perlu dipasang
            }
            // Jangan pasang modul TERKUNCI (development utk tenant non-dev / di luar
            // paket) — cegah baris orphan yang auto-aktif saat modul dipromosikan.
            if ($svc->moduleStatus($k, $tenant) === ModuleService::STATUS_LOCKED) {
                continue;
            }
            $sudahAktif = $tenant !== null && $svc->isEnabledForTenant($k, $tenant->getKey());
            if (! $sudahAktif) {
                $baru[] = $k;
            }
            $instan[$k] = ModuleSetupMap::ready($k);
        }

        if ($baru === []) {
            return [];
        }

        $tid = tenant('id');
        tenancy()->central(function () use ($baru, $instan, $tid): void {
            foreach ($baru as $k) {
                $row = TenantModule::firstOrNew(['tenant_id' => $tid, 'module_name' => $k]);
                $row->is_active = true;
                if ($row->installed_at === null) {
                    $row->installed_at = now();
                }
                // Modul tanpa langkah wajib → tandai siap langsung (1 klik).
                if (($instan[$k] ?? false) && $row->configured_at === null) {
                    $row->configured_at = now();
                }
                $row->save();
            }
        });

        // Buang cache set modul (baca-setelah-tulis konsisten tanpa andalkan redirect).
        app()->forgetInstance('__active_modules_'.$tid);
        app()->forgetInstance('__configured_modules_'.$tid);

        return $baru;
    }

    /**
     * Kumpulkan prasyarat (DFS) → modul, urut prasyarat duluan. Anti-siklus via $acc.
     *
     * @param  list<string>  $acc
     */
    private function resolveDeps(string $key, array &$acc): void
    {
        foreach (ModuleSetupMap::deps($key) as $dep) {
            if (! in_array($dep, $acc, true)) {
                $this->resolveDeps($dep, $acc);
            }
        }
        if (! in_array($key, $acc, true)) {
            $acc[] = $key;
        }
    }

    private function setupUrl(string $module): ?string
    {
        $unit = ModuleSetupMap::unit($module);
        if ($unit === null) {
            return null;
        }

        try {
            return SetupUnit::getUrl(['unit' => $unit, 'from' => 'toko']);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Progres langkah WAJIB modul: {selesai, total}. */
    private function langkahProgress(string $module): array
    {
        $steps = ModuleSetupMap::requiredSteps($module);
        $total = count($steps);
        $selesai = collect($steps)->where('done', true)->count();

        return ['selesai' => $selesai, 'total' => $total];
    }

    /** Label prasyarat awam, mis. "Butuh: Buku Besar (Akuntansi)". */
    private function labelPrasyarat(string $module): ?string
    {
        $deps = ModuleSetupMap::deps($module);
        if ($deps === []) {
            return null;
        }

        $labels = ModuleRegistry::query()->whereIn('key', $deps)->pluck('label')->all();

        return 'Butuh: '.implode(', ', $labels);
    }

    private function adaDisarankanTersedia(): bool
    {
        $svc = app(ModuleService::class);
        $tenant = tenancy()->initialized ? tenant() : null;

        foreach (ModuleRecommendationRegistry::untukTenantSaatIni() as $k) {
            if ($svc->moduleStatus($k, $tenant) === ModuleService::STATUS_AVAILABLE) {
                return true;
            }
        }

        return false;
    }

    /** Defense-in-depth: tegakkan izin di server, bukan hanya nav. */
    private function pastikanBoleh(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
