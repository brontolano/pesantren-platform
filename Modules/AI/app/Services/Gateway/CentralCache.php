<?php

namespace Modules\AI\Services\Gateway;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;

/**
 * Repository cache yang KEBAL terhadap CacheTenancyBootstrapper.
 *
 * Masalah yang dipecahkan: stancl/tenancy (config/tenancy.php 'bootstrappers')
 * MENUKAR singleton 'cache' dengan CacheManager bertag per-tenant saat tenancy
 * diinisialisasi — semua panggilan Cache:: (termasuk Cache::store(apapun))
 * dari dalam konteks tenant ikut tertag. State lintas-tenant seperti circuit
 * breaker provider AI (Ollama-nya SATU untuk semua tenant) dan cache keputusan
 * router akan TER-FRAGMENTASI per tenant bila lewat facade Cache:
 *  - ambang kegagalan breaker dihitung per tenant → sirkuit hampir tak pernah
 *    terbuka pada tenant bertrafik rendah padahal provider sedang mati;
 *  - ai:health (konteks central) membaca key tanpa tag → laporan menyesatkan.
 *
 * Solusi: bangun CacheManager BARU langsung dari config aplikasi (tidak lewat
 * binding 'cache' yang sudah ditukar tenancy), memo per-store di container —
 * satu repository global per proses, sama dari konteks central maupun tenant.
 */
final class CentralCache
{
    private const CONTAINER_KEY_PREFIX = 'modules.ai.central-cache.';

    /** @param string|null $store null = cache store default aplikasi. */
    public static function repository(?string $store = null): Repository
    {
        $app = app();
        $key = self::CONTAINER_KEY_PREFIX.($store ?? config('cache.default', 'default'));

        if (! $app->bound($key)) {
            // Manager segar dari config — TIDAK terpengaruh penukaran singleton
            // 'cache' oleh CacheTenancyBootstrapper.
            $app->instance($key, (new CacheManager($app))->store($store));
        }

        return $app->make($key);
    }
}
