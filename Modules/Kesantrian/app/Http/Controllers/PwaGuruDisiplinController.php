<?php

declare(strict_types=1);

namespace Modules\Kesantrian\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;

/**
 * WP-24 — PWA Guru Disiplin endpoint (kepengasuhan/musyrif mode).
 * Endpoints: daftar pelanggaran, catat, verifikasi, lihat poin.
 * Auth: middleware auth:sanctum, gate akses_pwa_guru.
 */
class PwaGuruDisiplinController extends Controller
{
    public function __construct(
        private readonly DisiplinService $disiplinService,
    ) {}

    /**
     * GET /api/pwa/guru/disiplin/daftar
     * List santri + pelanggaran bulan ini (grouping by santri).
     *
     * Query params:
     *   - bulan: 'YYYY-MM' (default: bulan sekarang)
     *   - status: 'diverifikasi' | 'dicatat' | 'all' (default: 'diverifikasi')
     */
    public function daftarPelanggaran(Request $request): JsonResponse
    {
        $bulan = $request->query('bulan', now()->format('Y-m'));
        $status = $request->query('status', 'diverifikasi');

        [$startOfMonth, $endOfMonth] = $this->rentangBulan($bulan);

        $query = PelanggaranSantri::query()
            ->whereBetween('waktu_kejadian', [$startOfMonth, $endOfMonth])
            ->with(['santri', 'jenis', 'pencatat', 'verifikator'])
            ->orderBy('waktu_kejadian', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $pelanggaran = $query->get();

        // Grouping by santri
        $daftar = $pelanggaran->groupBy('santri_id')->map(function ($grup) {
            $santri = $grup->first()?->santri;

            return [
                'santri_id' => $santri?->id,
                'nama' => $santri?->nama,
                'nis' => $santri?->nis,
                'kelas' => $santri?->kelas?->nama ?? '-',
                'poin_bulan_ini' => $this->hitungPoinBulan($grup),
                'total_pelanggaran' => $grup->count(),
                'pelanggaran' => $grup->map(fn ($p) => $this->formatPelanggaran($p))->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => $daftar,
            'meta' => [
                'bulan' => $bulan,
                'status_filter' => $status,
                'total_santri' => count($daftar),
            ],
        ]);
    }

    /**
     * POST /api/pwa/guru/disiplin/catatPelanggaran
     * Catat pelanggaran santri (jenis, alasan, poin).
     * Auto-verify jika OFF, verifikasi 2-lapis jika ON.
     *
     * Body:
     *   - santri_id: int
     *   - jenis_pelanggaran_id: int
     *   - alasan: string (kronologi)
     *   - waktu_kejadian?: ISO datetime (default: now)
     *   - lokasi?: string
     *   - foto_bukti?: string (path di storage)
     *   - client_ref?: string (idempotent key, optional)
     */
    public function catatPelanggaran(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'santri_id' => 'required|integer|exists:santri,id',
            'jenis_pelanggaran_id' => 'required|integer|exists:jenis_pelanggaran,id',
            'alasan' => 'required|string|min:5',
            'waktu_kejadian' => 'nullable|date_format:Y-m-d H:i:s',
            'lokasi' => 'nullable|string|max:255',
            'foto_bukti' => 'nullable|string',
            'client_ref' => 'nullable|string|max:255',
        ]);

        // Cek idempotency dengan client_ref jika ada
        if (! empty($validated['client_ref'])) {
            $existing = PelanggaranSantri::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('santri_id', $validated['santri_id'])
                ->where('jenis_pelanggaran_id', $validated['jenis_pelanggaran_id'])
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Pelanggaran sudah tercatat',
                    'data' => $this->formatPelanggaran($existing),
                ], 200);
            }
        }

        try {
            $santri = Santri::where('tenant_id', tenant('id'))->findOrFail($validated['santri_id']);
            $jenis = JenisPelanggaran::where('tenant_id', tenant('id'))->findOrFail($validated['jenis_pelanggaran_id']);

            $pelanggaran = $this->disiplinService->catat($santri, $jenis, auth()->user(), [
                'waktu_kejadian' => $validated['waktu_kejadian'] ?? null,
                'lokasi' => $validated['lokasi'] ?? null,
                'kronologi' => $validated['alasan'],
                'foto_bukti' => $validated['foto_bukti'] ?? null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pelanggaran berhasil dicatat',
                'data' => $this->formatPelanggaran($pelanggaran->refresh()),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/pwa/guru/disiplin/{pelanggaran_id}/verifikasi
     * Verifikasi pelanggaran pending (musyrif role required).
     * Status dicatat → diverifikasi.
     *
     * Body: (kosong, atau catatan verifikator optional)
     */
    public function verifikasiPelanggaran(Request $request, int $id): JsonResponse
    {
        try {
            $pelanggaran = PelanggaranSantri::where('tenant_id', tenant('id'))->findOrFail($id);

            if ($pelanggaran->status !== 'dicatat') {
                return response()->json([
                    'status' => 'error',
                    'message' => "Pelanggaran berstatus '{$pelanggaran->status}' tidak dapat diverifikasi",
                ], 422);
            }

            $pelanggaran = $this->disiplinService->verifikasi($pelanggaran, auth()->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Pelanggaran berhasil diverifikasi',
                'data' => $this->formatPelanggaran($pelanggaran->refresh()),
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/pwa/guru/disiplin/poinTerbaru
     * Total poin santri (agregat, respect setting masa_berlaku).
     *
     * Query params:
     *   - santri_id?: int (default: semua santri)
     */
    public function poinTerbaru(Request $request): JsonResponse
    {
        if ($request->has('santri_id')) {
            $santri = Santri::findOrFail($request->integer('santri_id'));
            $total = $this->disiplinService->totalPoin($santri);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'santri_id' => $santri->id,
                    'nama' => $santri->nama,
                    'total_poin' => $total,
                    'masa_berlaku' => $this->disiplinService->masaBerlaku(),
                    'ambang' => $this->disiplinService->ambang(),
                ],
            ]);
        }

        // Semua santri aktif di tenant
        $santri = Santri::query()
            ->where('status', 'aktif')
            ->with([
                'pelanggaran' => function ($query) {
                    $query->whereIn('status', PelanggaranSantri::STATUS_POIN_BERLAKU);
                },
            ])
            ->get();

        $daftar = $santri->map(function ($s) {
            return [
                'santri_id' => $s->id,
                'nama' => $s->nama,
                'nis' => $s->nis,
                'total_poin' => $this->disiplinService->totalPoin($s),
            ];
        })->all();

        return response()->json([
            'status' => 'success',
            'data' => $daftar,
            'meta' => [
                'total_santri' => count($daftar),
                'masa_berlaku' => $this->disiplinService->masaBerlaku(),
            ],
        ]);
    }

    // ===== Private helpers =====

    /**
     * @return array{0:\DateTime, 1:\DateTime}
     */
    private function rentangBulan(string $bulan): array
    {
        $date = Carbon::createFromFormat('Y-m', $bulan);

        return [
            $date->copy()->startOfMonth(),
            $date->copy()->endOfMonth(),
        ];
    }

    private function hitungPoinBulan($grup): int
    {
        return $grup
            ->filter(fn ($p) => in_array($p->status, PelanggaranSantri::STATUS_POIN_BERLAKU))
            ->sum(fn ($p) => $p->jenis?->poin ?? 0);
    }

    private function formatPelanggaran(PelanggaranSantri $pelanggaran): array
    {
        return [
            'id' => $pelanggaran->id,
            'santri_id' => $pelanggaran->santri_id,
            'santri_nama' => $pelanggaran->santri?->nama,
            'jenis_pelanggaran' => $pelanggaran->jenis?->nama,
            'kategori' => $pelanggaran->jenis?->kategori,
            'poin' => $pelanggaran->jenis?->poin,
            'alasan' => $pelanggaran->kronologi,
            'waktu_kejadian' => $pelanggaran->waktu_kejadian?->format('Y-m-d H:i:s'),
            'lokasi' => $pelanggaran->lokasi,
            'status' => $pelanggaran->status,
            'pencatat' => $pelanggaran->pencatat?->name,
            'verifikator' => $pelanggaran->verifikator?->name,
            'verified_at' => $pelanggaran->verified_at?->format('Y-m-d H:i:s'),
            'foto_bukti_url' => $pelanggaran->fotoBuktiUrl(),
            'created_at' => $pelanggaran->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
