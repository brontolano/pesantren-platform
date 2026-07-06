<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\TenantBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API publik jadwal sholat + arah kiblat + donasi (1-aplikasi / native Android).
 * Sejajar dgn TenantLandingPageController::ibadahPayload (sumber kebenaran web):
 * dihitung lokal dari lokasi tenant (TenantBranding), menghormati toggle CMS
 * per-fitur, tanpa auth, per-tenant (di-resolve dari domain). Defensif: lokasi
 * 0,0 / fitur dimatikan / error → data kosong, bukan 500.
 *
 * GPS HP (opsional): bila query `lat`/`lng` valid dikirim (mis. dari tombol
 * "Pakai lokasi saya" di app-shell), jadwal & kiblat dihitung dari koordinat
 * PERANGKAT, bukan lokasi pesantren — sehingga akurat di mana pun pengguna
 * berada DAN tetap jalan meski admin belum mengatur lokasi tenant. `tz` opsional
 * (offset jam dari UTC; default WIB via TenantBranding). Koordinat divalidasi
 * (rentang wajar, bukan 0,0) — invalid → jatuh ke lokasi tenant.
 */
class IbadahApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        [$lat, $lng, $tz, $dariGps] = $this->resolveLokasi($request);
        $adaLokasi = $lat !== 0.0 || $lng !== 0.0;

        $jadwal = [];
        $kiblat = null;

        if ($adaLokasi) {
            try {
                if ((bool) Setting::getTenantValue('beranda.tampil_jadwal_sholat', true)) {
                    $jadwal = app(PrayerTimesService::class)
                        ->fivePrayers($lat, $lng, $tz);
                }
                if ((bool) Setting::getTenantValue('beranda.tampil_arah_kiblat', true)) {
                    $kiblat = (int) round(app(QiblaService::class)->bearing($lat, $lng));
                }
            } catch (\Throwable $e) {
                $jadwal = [];
                $kiblat = null;
            }
        }

        $tampilDonasi = (bool) Setting::getTenantValue('beranda.tampil_donasi', true);

        return response()->json([
            'ok' => true,
            'data' => [
                'tanggal' => now()->toDateString(),
                'arahKiblat' => $kiblat,
                'jadwalSholat' => $jadwal,
                'donasiUrl' => $tampilDonasi ? '/donasi' : null,
                'dariGps' => $dariGps,
            ],
        ]);
    }

    /**
     * Tentukan (lat, lng, tz, dariGps). Prioritas: koordinat GPS perangkat dari
     * query bila valid; jika tidak, lokasi tenant. tz = offset jam UTC dari query
     * (dibatasi -12..14) atau default tenant.
     *
     * @return array{0:float,1:float,2:float,3:bool}
     */
    private function resolveLokasi(Request $request): array
    {
        $qLat = $request->query('lat');
        $qLng = $request->query('lng');

        if (is_numeric($qLat) && is_numeric($qLng)) {
            $lat = (float) $qLat;
            $lng = (float) $qLng;
            $valid = $lat >= -90.0 && $lat <= 90.0
                && $lng >= -180.0 && $lng <= 180.0
                && ($lat !== 0.0 || $lng !== 0.0);

            if ($valid) {
                $qTz = $request->query('tz');
                $tz = is_numeric($qTz) ? max(-12.0, min(14.0, (float) $qTz)) : TenantBranding::timezoneOffset();

                return [$lat, $lng, $tz, true];
            }
        }

        return [TenantBranding::latitude(), TenantBranding::longitude(), TenantBranding::timezoneOffset(), false];
    }
}
