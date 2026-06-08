<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\LaporanService;
use Modules\Pengadaan\Services\BelanjaService;
use Throwable;

/**
 * Endpoint JSON untuk PWA Keuangan Lapangan (staf). Tenant di-resolve oleh
 * middleware web (InitializeTenancyForWebRequests); auth via Sanctum/session.
 * Idempoten untuk mendukung offline-sync (client_ref).
 */
class KeuanganApiController extends Controller
{
    /** POST /api/keuangan/belanja — catat belanja cepat (pajak otomatis), idempoten. */
    public function belanja(Request $request, BelanjaService $belanjaService): JsonResponse
    {
        abort_unless($request->user()?->can('input_belanja') ?? false, 403);

        $data = $request->validate([
            'client_ref' => ['nullable', 'string', 'max:191'],
            'uraian' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'in:barang,jasa,sewa'],
            'dpp' => ['required', 'numeric', 'min:1'],
            'sumber_dana_id' => ['required', 'integer'],
            'divisi_id' => ['nullable', 'integer'],
            'akun_beban_id' => ['nullable', 'integer'],
            'akun_kas_id' => ['nullable', 'integer'],
            'vendor_npwp' => ['nullable', 'boolean'],
            'tanggal' => ['nullable', 'date'],
        ]);
        $data['created_by'] = $request->user()->id;

        try {
            $belanja = $belanjaService->buat($data);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        app(AuditLogger::class)->log('api.belanja_dibuat', $request->user(), $belanja, ['via' => 'pwa']);

        return response()->json([
            'id' => $belanja->id,
            'uraian' => $belanja->uraian,
            'dpp' => (float) $belanja->dpp,
            'ppn' => (float) $belanja->ppn_dipungut,
            'pph' => (float) $belanja->pph_dipungut,
            'total' => (float) $belanja->total,
            'status' => $belanja->status_approval,
        ], 201);
    }

    /** POST /api/keuangan/pemasukan — pembayaran tunai cepat → verifikasi → auto-jurnal. */
    public function pemasukan(Request $request, PembayaranService $pembayaranService): JsonResponse
    {
        abort_unless($request->user()?->canAny(['setor_tabungan', 'input_belanja', 'manage_keuangan']) ?? false, 403);

        $data = $request->validate([
            'tagihan_id' => ['required', 'integer'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'metode' => ['nullable', 'string', 'max:50'],
        ]);

        $tagihan = Tagihan::findOrFail($data['tagihan_id']);

        try {
            $pembayaran = $pembayaranService->inputPembayaran($tagihan, [
                'nominal' => $data['nominal'],
                'metode' => $data['metode'] ?? 'tunai',
                'created_by' => $request->user()->id,
            ]);
            $pembayaran = $pembayaranService->verifikasiPembayaran($pembayaran, $request->user()->id);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $pembayaran->id,
            'nominal' => (float) $pembayaran->nominal,
            'is_verified' => (bool) $pembayaran->is_verified,
        ], 201);
    }

    /** GET /api/keuangan/slip-saya — slip gaji milik PTK yang login (asatidz). */
    public function slipSaya(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view_slip_sendiri') ?? false, 403);

        $slip = \Modules\Payroll\Models\SlipGaji::where('users_id', $request->user()->id)
            ->where('status', 'final')->orderByDesc('periode')->get()
            ->map(fn ($s) => [
                'periode' => $s->periode,
                'bruto' => (float) $s->bruto,
                'pph21' => (float) $s->pph21_dipotong,
                'neto' => (float) $s->neto,
            ]);

        return response()->json(['slip' => $slip]);
    }

    /** GET /api/keuangan/saldo — saldo kas/bank konsolidasi. */
    public function saldo(BukuBesarService $bukuBesar): JsonResponse
    {
        $bku = $bukuBesar->bku();

        return response()->json(['saldo_kas' => $bku['saldo_akhir']]);
    }

    /** GET /api/keuangan/dashboard-pimpinan — KPI ringkas (L1). */
    public function dashboardPimpinan(LaporanService $laporan, BukuBesarService $bukuBesar): JsonResponse
    {
        $lr = $laporan->labaRugi();
        $saldoKas = $bukuBesar->bku()['saldo_akhir'];

        $tunggakan = Tagihan::belumLunas()->get()->sum(fn (Tagihan $t) => $t->sisa_tagihan);

        return response()->json([
            'kas_konsolidasi' => $saldoKas,
            'total_pendapatan' => $lr['total_pendapatan'],
            'total_beban' => $lr['total_beban'],
            'surplus_defisit' => $lr['surplus_defisit'],
            'tunggakan' => round((float) $tunggakan, 2),
        ]);
    }
}
