<?php

namespace Modules\Pembiayaan\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Pembiayaan\Models\CeklisPiket;
use Modules\Pembiayaan\Models\LaporanKerusakan;
use Modules\Pembiayaan\Services\SarprasService;
use Throwable;

/**
 * PWA Guru Sarpras — WP-33.
 * - Lapor kerusakan: lokasi + deskripsi + foto WAJIB (pdf/jpg/png, stored-XSS).
 * - Ceklis piket: scan QR area (keyboard-wedge) → status + catatan.
 */
class PwaGuruSarprasController extends Controller
{
    public function __construct(private SarprasService $service) {}

    /**
     * POST /api/pwa/guru/sarpras/lapor-kerusakan  (multipart)
     * { lokasi, deskripsi, foto: file pdf/jpg/png }
     */
    public function laporKerusakan(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $data = $request->validate([
            'lokasi' => ['required', 'string', 'max:200'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            // Foto WAJIB; double-gate: mimes (ekstensi) + mimetypes (isi file).
            'foto' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ]);

        try {
            $laporan = $this->service->laporKerusakan($user, $data, $request->file('foto'));

            return response()->json([
                'id' => $laporan->id,
                'lokasi' => $laporan->lokasi,
                'status' => $laporan->status,
                'foto_url' => $laporan->fotoUrl(),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/pwa/guru/sarpras/laporan-saya
     * Daftar laporan kerusakan milik pelapor (30 hari terakhir).
     */
    public function laporanSaya(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        return response()->json(
            LaporanKerusakan::query()
                ->where('pelapor_id', $user->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (LaporanKerusakan $l) => [
                    'id' => $l->id,
                    'lokasi' => $l->lokasi,
                    'deskripsi' => $l->deskripsi,
                    'status' => $l->status,
                    'foto_url' => $l->fotoUrl(),
                    'dibuat_pada' => $l->created_at->format('Y-m-d H:i:s'),
                ])
        );
    }

    /**
     * POST /api/pwa/guru/sarpras/piket/scan
     * { kode_qr, status: bersih|kotor|rusak, catatan? }
     * Kode QR dari scanner keyboard-wedge (pola gerbang).
     */
    public function scanPiket(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $data = $request->validate([
            'kode_qr' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:'.implode(',', CeklisPiket::STATUS)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $ceklis = $this->service->scanPiket($user, $data['kode_qr'], $data['status'], $data['catatan'] ?? null);

            return response()->json([
                'id' => $ceklis->id,
                'area_id' => $ceklis->area_id,
                'area_nama' => $ceklis->area?->nama,
                'tanggal' => $ceklis->tanggal->format('Y-m-d'),
                'jam' => $ceklis->jam->format('H:i:s'),
                'status' => $ceklis->status,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
