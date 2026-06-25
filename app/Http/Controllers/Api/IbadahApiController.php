<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\TenantBranding;
use Illuminate\Http\JsonResponse;

/**
 * API publik jadwal sholat + arah kiblat + donasi (1-aplikasi / native Android).
 * Sejajar dgn TenantLandingPageController::ibadahPayload (sumber kebenaran web):
 * dihitung lokal dari lokasi tenant (TenantBranding), menghormati toggle CMS
 * per-fitur, tanpa auth, per-tenant (di-resolve dari domain). Defensif: lokasi
 * 0,0 / fitur dimatikan / error → data kosong, bukan 500.
 */
class IbadahApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $lat = TenantBranding::latitude();
        $lng = TenantBranding::longitude();
        $adaLokasi = $lat !== 0.0 || $lng !== 0.0;

        $jadwal = [];
        $kiblat = null;

        if ($adaLokasi) {
            try {
                if ((bool) Setting::getTenantValue('beranda.tampil_jadwal_sholat', true)) {
                    $jadwal = app(PrayerTimesService::class)
                        ->fivePrayers($lat, $lng, TenantBranding::timezoneOffset());
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
            ],
        ]);
    }
}
