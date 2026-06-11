<?php

namespace Modules\Kesehatan\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Kesehatan\Services\KesehatanService;
use Modules\Kesantrian\Models\Santri;
use Throwable;

/**
 * PWA Guru UKS — Register kunjungan santri ke UKS.
 * WP-27 — Catat keluhan, tindakan, obat, status istirahat/rujukan, auto-sync presensi.
 */
class PwaGuruUksController extends Controller
{
    public function __construct(private KesehatanService $service) {}

    /**
     * GET /api/pwa/guru/uks/istirahat
     * Daftar santri yang sedang istirahat hari ini.
     */
    public function daftarIstirahat(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        try {
            $istirahat = $this->service->getDaftarIstirahat();

            return response()->json(
                $istirahat->map(fn (KunjunganUks $k) => [
                    'id' => $k->id,
                    'santri_id' => $k->santri_id,
                    'santri_nama' => $k->santri?->nama,
                    'keluhan' => $k->keluhan,
                    'jam_masuk' => $k->jam_masuk->format('H:i:s'),
                    'jam_keluar' => $k->jam_keluar?->format('H:i:s'),
                    'status' => $k->status,
                    'pencatat_nama' => $k->pencatat?->name,
                    'dibuat_pada' => $k->created_at->format('Y-m-d H:i:s'),
                ])
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/pwa/guru/uks/rujukan-terbuka
     * Daftar rujukan yang belum ditangani.
     */
    public function daftarRujukanTerbuka(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        try {
            $rujukan = $this->service->getDaftarRujukanTerbuka();

            return response()->json(
                $rujukan->map(fn (KunjunganUks $k) => [
                    'id' => $k->id,
                    'santri_id' => $k->santri_id,
                    'santri_nama' => $k->santri?->nama,
                    'tanggal' => $k->tanggal->format('Y-m-d'),
                    'keluhan' => $k->keluhan,
                    'rujukan_ke' => $k->rujukan_ke,
                    'rujukan_biaya' => $k->rujukan_biaya ? (float) $k->rujukan_biaya : null,
                    'jam_masuk' => $k->jam_masuk->format('H:i:s'),
                    'pencatat_nama' => $k->pencatat?->name,
                    'dibuat_pada' => $k->created_at->format('Y-m-d H:i:s'),
                ])
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/pwa/guru/uks/catat-kunjungan
     * Catat kunjungan santri ke UKS.
     * Idempoten via client_ref (duplicate request → update saja).
     *
     * Request body:
     * {
     *   "santri_id": 123,
     *   "keluhan": "Demam tinggi",
     *   "tindakan": "Istirahat dan kompres",
     *   "obat": "Paracetamol 500mg",
     *   "status": "istirahat|pulang|rujuk",
     *   "rujukan_ke": "Puskesmas Klinik (jika status=rujuk)",
     *   "rujukan_biaya": 50000 (jika status=rujuk),
     *   "client_ref": "uks-20260611-santo-001" (untuk idempotency)
     * }
     */
    public function catatKunjungan(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'keluhan' => ['nullable', 'string', 'max:500'],
            'tindakan' => ['nullable', 'string', 'max:1000'],
            'obat' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:' . implode(',', KunjunganUks::STATUS)],
            'rujukan_ke' => ['nullable', 'string', 'max:200'],
            'rujukan_biaya' => ['nullable', 'numeric', 'min:0'],
            'client_ref' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $santri = Santri::findOrFail($data['santri_id']);

            $kunjungan = $this->service->catatKunjungan(
                $santri,
                now()->toDateString(),
                $data['status'],
                [
                    'keluhan' => $data['keluhan'] ?? null,
                    'tindakan' => $data['tindakan'] ?? null,
                    'obat' => $data['obat'] ?? null,
                    'rujukan_ke' => $data['rujukan_ke'] ?? null,
                    'rujukan_biaya' => $data['rujukan_biaya'] ?? null,
                    'pencatat_id' => $user->id,
                    'jam_masuk' => now()->format('H:i:s'),
                ]
            );

            // Trigger notifikasi ke wali jika rujukan
            if ($kunjungan->status === 'rujuk') {
                try {
                    app(\Modules\Notifikasi\Services\NotifDispatcher::class)->kirim('uks_rujukan', [
                        'data' => [
                            'santri_nama' => $santri->nama,
                            'rujukan_ke' => $kunjungan->rujukan_ke,
                            'rujukan_biaya' => $kunjungan->rujukan_biaya,
                            'pencatat_nama' => $user->name,
                        ],
                        'penerima' => [
                            [
                                'target' => $santri->no_hp_wali ?? '',
                                'variables' => [],
                            ],
                        ],
                    ]);
                } catch (Throwable) {
                    // Notif failure tidak block response
                }
            }

            return response()->json([
                'id' => $kunjungan->id,
                'santri_id' => $kunjungan->santri_id,
                'tanggal' => $kunjungan->tanggal->format('Y-m-d'),
                'jam_masuk' => $kunjungan->jam_masuk->format('H:i:s'),
                'status' => $kunjungan->status,
                'rujukan_ke' => $kunjungan->rujukan_ke,
                'rujukan_biaya' => $kunjungan->rujukan_biaya ? (float) $kunjungan->rujukan_biaya : null,
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/pwa/guru/uks/{kunjungan_id}/tutup
     * Tandai jam keluar kunjungan UKS.
     *
     * Request body:
     * {
     *   "jam_keluar": "14:30:00" (opsional, default = jam sekarang)
     * }
     */
    public function tutupKunjungan(Request $request, int $kunjungan_id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $data = $request->validate([
            'jam_keluar' => ['nullable', 'date_format:H:i:s'],
        ]);

        try {
            $kunjungan = KunjunganUks::findOrFail($kunjungan_id);

            $kunjungan = $this->service->tutupKunjungan(
                $kunjungan,
                $data['jam_keluar'] ?? null
            );

            return response()->json([
                'id' => $kunjungan->id,
                'santri_id' => $kunjungan->santri_id,
                'tanggal' => $kunjungan->tanggal->format('Y-m-d'),
                'jam_masuk' => $kunjungan->jam_masuk->format('H:i:s'),
                'jam_keluar' => $kunjungan->jam_keluar->format('H:i:s'),
                'status' => $kunjungan->status,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/pwa/guru/uks/{santri_id}/riwayat
     * Dapatkan riwayat kunjungan santri ke UKS (30 hari terakhir).
     */
    public function riwayat(Request $request, int $santri_id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        try {
            $santri = Santri::findOrFail($santri_id);

            $dari = now()->subDays(30)->toDateString();
            $sampai = now()->toDateString();

            $kunjungan = $this->service->getDaftarKunjungan($santri, $dari, $sampai);

            return response()->json(
                $kunjungan->map(fn (KunjunganUks $k) => [
                    'id' => $k->id,
                    'tanggal' => $k->tanggal->format('Y-m-d'),
                    'jam_masuk' => $k->jam_masuk->format('H:i:s'),
                    'jam_keluar' => $k->jam_keluar?->format('H:i:s'),
                    'keluhan' => $k->keluhan,
                    'tindakan' => $k->tindakan,
                    'obat' => $k->obat,
                    'status' => $k->status,
                    'rujukan_ke' => $k->rujukan_ke,
                    'rujukan_biaya' => $k->rujukan_biaya ? (float) $k->rujukan_biaya : null,
                    'pencatat_nama' => $k->pencatat?->name,
                ])
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
