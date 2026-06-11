<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Keuangan\Models\SetorKelilingItem;
use Modules\Keuangan\Models\SetorKelilingSesi;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\SetorKelilingService;
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

    /** GET /api/keuangan/setor-keliling/kamar — daftar blok+kamar untuk petugas keliling. */
    public function setorKelilingKamar(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('setor_tabungan') ?? false, 403);

        $blok = BlokAsrama::query()
            ->where('is_active', true)
            ->with(['kamar' => fn ($q) => $q->where('is_active', true)->withCount('penempatanAktif')->orderBy('nomor_kamar')])
            ->orderBy('nama_blok')
            ->get()
            ->map(fn (BlokAsrama $b) => [
                'id' => $b->id,
                'nama_blok' => $b->nama_blok,
                'kamar' => $b->kamar->map(fn ($k) => [
                    'id' => $k->id,
                    'nomor_kamar' => $k->nomor_kamar,
                    'jumlah_santri' => $k->penempatan_aktif_count,
                ])->values(),
            ]);

        return response()->json(['blok' => $blok]);
    }

    /** GET /api/keuangan/setor-keliling/kamar/{kamar}/santri — penghuni aktif kamar. */
    public function setorKelilingSantri(Request $request, int $kamar): JsonResponse
    {
        abort_unless($request->user()?->can('setor_tabungan') ?? false, 403);

        $santri = PenempatanAsrama::query()
            ->where('kamar_id', $kamar)
            ->where('is_current', true)
            ->with('santri:id,nama,nis')
            ->get()
            ->filter(fn (PenempatanAsrama $p) => $p->santri !== null)
            ->map(fn (PenempatanAsrama $p) => [
                'id' => $p->santri->id,
                'nama' => $p->santri->nama,
                'nis' => $p->santri->nis,
            ])
            ->values();

        return response()->json(['santri' => $santri]);
    }

    /**
     * POST /api/keuangan/setor-keliling/setoran — catat satu setoran DRAF via
     * SetorKelilingService (saldo TIDAK tersentuh; posting saat bendahara
     * approve). Idempoten: client_ref unik per item, duplikat → 200 idempoten.
     */
    public function setorKelilingSetoran(Request $request, SetorKelilingService $svc): JsonResponse
    {
        abort_unless($request->user()?->can('setor_tabungan') ?? false, 403);

        $data = $request->validate([
            'client_ref' => ['required', 'string', 'max:191'],
            'santri_id' => ['required', 'integer'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'catatan' => ['nullable', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ]);

        // Santri WAJIB ada di tenant ini (query ter-scope BelongsToTenant) —
        // tanpa ini santri_id tebakan lintas-tenant/asal-asalan ikut tercatat
        // lalu uangnya terposting saat bendahara approve.
        if (! \Modules\Kesantrian\Models\Santri::query()->whereKey((int) $data['santri_id'])->exists()) {
            return response()->json(['message' => 'Santri tidak ditemukan.'], 422);
        }

        // Dedup cepat (jalur normal retry offline-sync).
        $existing = SetorKelilingItem::where('client_ref', $data['client_ref'])->first();
        if ($existing !== null) {
            return $this->setorKelilingItemResponse($existing, true);
        }

        try {
            $sesi = $this->sesiDrafHariIni($request->user()->id, $data['lokasi'] ?? null);
            $item = $svc->tambahItem($sesi, (int) $data['santri_id'], (float) $data['nominal'], $data['catatan'] ?? null, $data['client_ref']);
        } catch (QueryException $e) {
            // Race dobel-kirim: unique index (tenant, client_ref) menolak insert
            // kedua → kembalikan item yang menang sebagai sukses-idempoten.
            $existing = SetorKelilingItem::where('client_ref', $data['client_ref'])->first();
            if ($existing !== null) {
                return $this->setorKelilingItemResponse($existing, true);
            }
            throw $e;
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        app(AuditLogger::class)->log('api.setor_keliling_item', $request->user(), $item, ['via' => 'pwa']);

        return $this->setorKelilingItemResponse($item, false);
    }

    /** GET /api/keuangan/setor-keliling/sesi — ringkasan sesi draf hari ini. */
    public function setorKelilingSesi(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('setor_tabungan') ?? false, 403);

        $sesi = SetorKelilingSesi::query()
            ->where('petugas_id', $request->user()->id)
            ->whereDate('tanggal', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($sesi === null) {
            return response()->json(['sesi' => null]);
        }

        return response()->json(['sesi' => [
            'id' => $sesi->id,
            'status' => $sesi->status,
            'total' => (float) $sesi->total,
            'jumlah_item' => $sesi->items()->count(),
        ]]);
    }

    /**
     * POST /api/keuangan/setor-keliling/ajukan — kunci SEMUA sesi draf milik
     * petugas (termasuk draf kemarin yang tertinggal, agar uang tunai yang
     * sudah dipegang petugas tidak macet tanpa jalur pengajuan).
     */
    public function setorKelilingAjukan(Request $request, SetorKelilingService $svc): JsonResponse
    {
        abort_unless($request->user()?->can('setor_tabungan') ?? false, 403);

        $drafBerisi = SetorKelilingSesi::query()
            ->where('petugas_id', $request->user()->id)
            ->where('status', 'draf')
            ->has('items')
            ->orderBy('id')
            ->get();

        if ($drafBerisi->isEmpty()) {
            return response()->json(['message' => 'Tidak ada sesi draf berisi setoran.'], 422);
        }

        $terakhir = null;
        $totalSemua = 0.0;
        try {
            foreach ($drafBerisi as $sesi) {
                $terakhir = $svc->ajukan($sesi);
                $totalSemua += (float) $terakhir->total;
            }
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $terakhir->id,
            'status' => $terakhir->status,
            'total' => (float) $terakhir->total,
            'sesi_diajukan' => $drafBerisi->count(),
            'total_semua' => $totalSemua,
        ]);
    }

    /** Sesi draf milik petugas untuk hari ini — pakai yang ada atau buat baru. */
    private function sesiDrafHariIni(int $petugasId, ?string $lokasi): SetorKelilingSesi
    {
        $sesi = SetorKelilingSesi::query()
            ->where('petugas_id', $petugasId)
            ->whereDate('tanggal', now()->toDateString())
            ->where('status', 'draf')
            ->orderByDesc('id')
            ->first();

        return $sesi ?? app(SetorKelilingService::class)->buatSesi($petugasId, $lokasi);
    }

    private function setorKelilingItemResponse(SetorKelilingItem $item, bool $idempotent): JsonResponse
    {
        return response()->json([
            'id' => $item->id,
            'sesi_id' => $item->sesi_id,
            'santri_id' => $item->santri_id,
            'nominal' => (float) $item->nominal,
            'idempotent' => $idempotent,
        ], $idempotent ? 200 : 201);
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
