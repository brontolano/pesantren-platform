<?php

namespace Modules\Kegiatan\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Services\IzinSantriService;
use Modules\Kegiatan\Services\SuratIzinSantriService;
use Throwable;

class PwaGuruIzinController extends Controller
{
    /**
     * GET /api/pwa/guru/izin/antrean
     * Daftar izin yang menunggu approval (diajukan status).
     * Hanya guru/musyrif yang dapat akses.
     */
    public function antrean(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $izins = IzinSantri::where('tenant_id', tenant('id'))
            ->where('status', 'diajukan')
            ->with(['santri'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(
            $izins->map(fn (IzinSantri $i) => [
                'id' => $i->id,
                'santri_nama' => $i->santri?->nama,
                'santri_id' => $i->santri_id,
                'jenis_izin' => $i->jenis_izin,
                'tanggal_mulai' => $i->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $i->tanggal_selesai->format('Y-m-d'),
                'alasan' => $i->alasan,
                'diajukan_pada' => $i->created_at->format('Y-m-d H:i:s'),
            ])
        );
    }

    /**
     * POST /api/pwa/guru/izin/{izin_id}/setujui
     * Setujui izin pulang.
     */
    public function setujui(
        Request $request,
        int $izin_id,
        IzinSantriService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $izin = IzinSantri::findOrFail($izin_id);

        try {
            $izin = $service->setujui($izin, $user);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Kirim notifikasi ke wali
        try {
            app(\Modules\Notifikasi\Services\NotifDispatcher::class)->kirim('izin_disetujui', [
                'data' => [
                    'santri_nama' => $izin->santri?->nama,
                    'disetujui_oleh' => $user->name,
                ],
                'penerima' => [
                    [
                        'target' => $izin->santri?->wali?->contact_phone ?? '',
                        'variables' => [],
                    ],
                ],
            ]);
        } catch (Throwable) {
            // Notif failure tidak block response
        }

        return response()->json([
            'id' => $izin->id,
            'status' => $izin->status,
            'disetujui_oleh' => $user->name,
        ]);
    }

    /**
     * POST /api/pwa/guru/izin/{izin_id}/tolak
     * Tolak izin dengan alasan.
     */
    public function tolak(
        Request $request,
        int $izin_id,
        IzinSantriService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $data = $request->validate([
            'catatan' => ['required', 'string', 'max:500'],
        ]);

        $izin = IzinSantri::findOrFail($izin_id);

        try {
            $izin = $service->tolak($izin, $data['catatan'], $user);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Kirim notifikasi ke wali
        try {
            app(\Modules\Notifikasi\Services\NotifDispatcher::class)->kirim('izin_ditolak', [
                'data' => [
                    'santri_nama' => $izin->santri?->nama,
                    'alasan' => $data['catatan'],
                ],
                'penerima' => [
                    [
                        'target' => $izin->santri?->wali?->contact_phone ?? '',
                        'variables' => [],
                    ],
                ],
            ]);
        } catch (Throwable) {
            // Notif failure tidak block response
        }

        return response()->json([
            'id' => $izin->id,
            'status' => $izin->status,
        ]);
    }

    /**
     * GET /api/pwa/guru/izin/{izin_id}/surat-pdf
     * Download surat PDF izin dengan QR code (idempoten).
     */
    public function suratPdf(
        Request $request,
        int $izin_id,
        SuratIzinSantriService $suratService
    ): Response {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('akses_pwa_guru'), 403);

        $izin = IzinSantri::findOrFail($izin_id);

        try {
            $pdfBytes = $suratService->suratPdf($izin);
        } catch (Throwable $e) {
            return response($e->getMessage(), 422);
        }

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"izin-{$izin->id}.pdf\"",
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
