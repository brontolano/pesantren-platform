<?php

declare(strict_types=1);

namespace App\Support\Pwa;

/**
 * Kill-switch service worker (template F0, dipakai ulang di F5 untuk
 * guru/wali/keuangan): saat flag PWA dimatikan, endpoint sw.js TIDAK cukup
 * berhenti diregistrasi — SW yang TERLANJUR terpasang di perangkat hidup
 * selamanya. Solusinya: sajikan skrip SELF-DESTRUCT yang meng-unregister
 * dirinya sendiri, menghapus Cache Storage miliknya (by-prefix), dan
 * melewatkan semua fetch ke jaringan sampai unregister selesai.
 */
final class SelfDestructSw
{
    /**
     * @param  string  $cachePrefix  Prefix cache milik SW ini (mis. 'root-shell-').
     */
    public static function script(string $cachePrefix): string
    {
        $prefix = json_encode($cachePrefix, JSON_THROW_ON_ERROR);

        return <<<JS
        // KILL-SWITCH: flag PWA dimatikan — SW ini menghancurkan dirinya sendiri.
        const CACHE_PREFIX = {$prefix};

        self.addEventListener('install', (event) => {
            event.waitUntil(self.skipWaiting());
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil((async () => {
                const keys = await caches.keys();
                await Promise.all(
                    keys
                        .filter((key) => key.startsWith(CACHE_PREFIX))
                        .map((key) => caches.delete(key)),
                );
                await self.registration.unregister();
                await self.clients.claim();
            })());
        });

        // Fetch no-op: jangan intercept apa pun — semua langsung ke jaringan.
        self.addEventListener('fetch', () => {});
        JS;
    }

    /** @return array<string, string> Header respons yang tepat untuk skrip SW. */
    public static function headers(string $scope = '/'): array
    {
        return [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Service-Worker-Allowed' => $scope,
        ];
    }
}
