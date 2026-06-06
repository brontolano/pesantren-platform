<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\JadwalUjian;

/**
 * API publik headless per tenant (terproteksi api.key + throttle:public-api).
 * Read-only; data dari tenant aktif (di-resolve oleh domain). Kontrak: docs/INTEGRASI-API.md.
 */
class TenantApiController extends Controller
{
    /** Profil ringkas tenant + branding. */
    public function profile(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => [
            'nama' => tenant()?->name,
            'branding' => [
                'nama' => Setting::getTenantStringValue('branding_name'),
                'tagline' => Setting::getTenantStringValue('branding_tagline'),
                'warna_primer' => Setting::getTenantStringValue('branding_primary_color'),
                'warna_sekunder' => Setting::getTenantStringValue('branding_secondary_color'),
                'logo' => Setting::getTenantStringValue('branding_logo'),
            ],
        ]]);
    }

    /** Statistik ringkas (santri & pendaftar). */
    public function statistics(): JsonResponse
    {
        $data = [];

        if (class_exists(Santri::class)) {
            $data['santri_aktif'] = Santri::query()->where('status', 'aktif')->count();
            $data['total_santri'] = Santri::query()->count();
        }

        if (class_exists(CalonSantri::class)) {
            $data['pendaftar'] = CalonSantri::query()->count();
            $data['pendaftar_lulus'] = CalonSantri::query()->where('status_pendaftaran', 'lulus')->count();
        }

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** Jadwal ujian PPDB (publik untuk peserta/website). */
    public function jadwalPpdb(): JsonResponse
    {
        $data = [];

        if (class_exists(JadwalUjian::class)) {
            $data = JadwalUjian::query()
                ->orderBy('tanggal')
                ->orderBy('waktu_mulai')
                ->get()
                ->map(static fn (JadwalUjian $j): array => [
                    'kategori' => (string) $j->kategori_ujian,
                    'tanggal' => $j->tanggal?->format('Y-m-d'),
                    'waktu_mulai' => $j->waktu_mulai ? substr((string) $j->waktu_mulai, 0, 5) : null,
                    'waktu_selesai' => $j->waktu_selesai ? substr((string) $j->waktu_selesai, 0, 5) : null,
                    'ruang' => (string) ($j->ruang ?? ''),
                ])
                ->all();
        }

        return response()->json(['ok' => true, 'data' => $data]);
    }
}
