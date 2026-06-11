<?php

namespace Modules\Kegiatan\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Services\IzinSantriService;
use Modules\Kesantrian\Models\Santri;
use Throwable;

class PwaWaliIzinController extends Controller
{
    /**
     * POST /api/pwa/wali/{santri_id}/izin-ajukan
     * Ajukan izin pulang dari Portal Wali (orang tua).
     *
     * Payload: {jenis_izin, tanggal_mulai, tanggal_selesai, alasan, client_ref?}
     * Idempoten via client_ref.
     */
    public function ajukan(
        Request $request,
        int $santri_id,
        IzinSantriService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $santri = Santri::findOrFail($santri_id);

        // Wali hanya bisa ajukan untuk anaknya sendiri
        $waliSantri = $user->santriChildren ?? [];
        abort_unless(collect($waliSantri)->contains('id', $santri->id), 403);

        $data = $request->validate([
            'jenis_izin' => ['required', 'in:pulang,sakit,keluar_kota,kunjungan'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date'],
            'alasan' => ['required', 'string', 'max:1000'],
            'client_ref' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $izin = $service->ajukan(array_merge($data, ['santri_id' => $santri_id]));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Kirim notifikasi ke musyrif
        try {
            app(\Modules\Notifikasi\Services\NotifDispatcher::class)->kirim('izin_diajukan', [
                'data' => [
                    'santri_nama' => $santri->nama,
                    'jenis_izin' => $data['jenis_izin'],
                    'tanggal_mulai' => $data['tanggal_mulai'],
                ],
                'target_group' => 'role:musyrif',
            ]);
        } catch (Throwable) {
            // Notif failure tidak boleh block response
        }

        return response()->json([
            'id' => $izin->id,
            'status' => $izin->status,
            'tanggal_mulai' => $izin->tanggal_mulai->format('Y-m-d'),
            'tanggal_selesai' => $izin->tanggal_selesai->format('Y-m-d'),
        ], 201);
    }

    /**
     * GET /api/pwa/wali/{santri_id}/izin-list
     * Daftar izin pending + approved untuk satu santri (Portal Wali).
     */
    public function list(Request $request, int $santri_id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $santri = Santri::findOrFail($santri_id);

        // Wali hanya bisa lihat anaknya sendiri
        $waliSantri = $user->santriChildren ?? [];
        abort_unless(collect($waliSantri)->contains('id', $santri->id), 403);

        $izins = IzinSantri::where('tenant_id', tenant('id'))
            ->where('santri_id', $santri_id)
            ->whereIn('status', ['diajukan', 'disetujui', 'ditolak'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(
            $izins->map(fn (IzinSantri $i) => [
                'id' => $i->id,
                'jenis_izin' => $i->jenis_izin,
                'tanggal_mulai' => $i->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $i->tanggal_selesai->format('Y-m-d'),
                'status' => $i->status,
                'disetujui_oleh' => $i->disetujuiOleh?->name,
                'sudah_kembali' => $i->sudah_kembali,
                'catatan_pengurus' => $i->catatan_pengurus,
            ])
        );
    }

    /**
     * POST /api/pwa/wali/izin/{izin_id}/tandai-kembali
     * Tandai sudah kembali dari izin.
     */
    public function tandaiKembali(
        Request $request,
        int $izin_id,
        IzinSantriService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $izin = IzinSantri::where('tenant_id', tenant('id'))->findOrFail($izin_id);

        // Wali hanya bisa tandai anaknya sendiri
        $waliSantri = $user->santriChildren ?? [];
        abort_unless(collect($waliSantri)->contains('id', $izin->santri_id), 403);

        try {
            $izin = $service->tandaiKembali($izin);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $izin->id,
            'sudah_kembali' => $izin->sudah_kembali,
            'tanggal_kembali_aktual' => $izin->tanggal_kembali_aktual?->format('Y-m-d H:i:s'),
        ]);
    }
}
