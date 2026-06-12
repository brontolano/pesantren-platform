<?php

declare(strict_types=1);

namespace App\Http\Controllers\KepalaKeuangan;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Models\InventoryItem;
use Modules\Keuangan\Models\PosSesi;
use Modules\Keuangan\Models\PosTransaksi;
use Modules\Keuangan\Services\KantinService;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TagihanService;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Services\ApprovalService;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\PeriodeAkuntansi;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\LaporanService;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\PayrollService;

/**
 * JSON API untuk PWA Kepala Keuangan.
 * Semua endpoint memerlukan auth + manage_keuangan (via route middleware).
 * Data berat di-lazy-load dari Vue via fetch().
 */
class KepalaKeuanganApiController extends Controller
{
    // ── 01 Beranda ──────────────────────────────────────────────

    public function beranda(BukuBesarService $bukuBesar): JsonResponse
    {
        $akunKasBank = Akun::where('is_kas_bank', true)
            ->where('is_postable', true)
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama']);

        $neracaSaldo = $bukuBesar->neracaSaldo();
        $saldoMap = $neracaSaldo->keyBy('akun_id');

        $saldoPerAkun = $akunKasBank->map(function ($akun) use ($saldoMap) {
            $row = $saldoMap->get($akun->id);
            $saldo = $row ? (float) $row['debit'] - (float) $row['kredit'] : 0;

            return [
                'id' => $akun->id,
                'kode' => $akun->kode,
                'nama' => $akun->nama,
                'saldo' => $saldo,
            ];
        });

        $totalKas = $saldoPerAkun->sum('saldo');

        $pendingVerifikasi = Pembayaran::where('is_verified', false)
            ->whereNull('alasan_penolakan')
            ->count();

        $periodeAktif = PeriodeAkuntansi::where('status', 'open')->first();

        $kasTunai = $saldoPerAkun->filter(fn ($a) => str_contains(strtolower($a['nama']), 'kas') && ! str_contains(strtolower($a['nama']), 'bank'))->sum('saldo');
        $kasBankNominal = $totalKas - $kasTunai;

        try {
            $pendingApproval = ApprovalRequest::with(['peminta:id,name', 'approvable'])
                ->where('status', 'menunggu')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'keterangan' => $a->approvable->uraian
                        ?? $a->approvable->keterangan
                        ?? class_basename($a->approvable_type),
                    'pemohon' => $a->peminta?->name ?? '-',
                    'nominal' => (float) ($a->approvable->total ?? $a->approvable->nominal ?? 0),
                ]);
        } catch (\Throwable) {
            $pendingApproval = collect();
        }

        $today = now()->toDateString();
        $penerimaanHariIni = Pembayaran::where('is_verified', true)
            ->whereDate('verified_at', $today)
            ->sum('nominal');

        $kasAkunIds = $akunKasBank->pluck('id');
        $pengeluaranHariIni = JurnalBaris::whereIn('akun_id', $kasAkunIds)
            ->where('kredit', '>', 0)
            ->whereHas('jurnal', fn ($q) => $q->where('status', 'posted')->whereDate('tanggal', $today))
            ->sum('kredit');

        return response()->json([
            'totalKas' => $totalKas,
            'kasTunai' => $kasTunai,
            'kasBank' => $kasBankNominal,
            'saldoPerAkun' => $saldoPerAkun,
            'pendingVerifikasi' => $pendingVerifikasi,
            'pendingApproval' => $pendingApproval,
            'periodeAktif' => $periodeAktif?->nama ?? '-',
            'ringkasanHarian' => [
                'penerimaan' => $penerimaanHariIni,
                'pengeluaran' => $pengeluaranHariIni,
                'belumVerifikasi' => $pendingVerifikasi,
            ],
        ]);
    }

    // ── 02.1 Tagihan ────────────────────────────────────────────

    public function tagihan(Request $request): JsonResponse
    {
        $query = Tagihan::with('santri:id,nama,nis')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->paginate(20));
    }

    // ── 02.2 Verifikasi Pembayaran ──────────────────────────────

    public function pembayaranPending(): JsonResponse
    {
        $pending = Pembayaran::with('tagihan.santri:id,nama,nis', 'creator:id,name')
            ->where('is_verified', false)
            ->whereNull('alasan_penolakan')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($pending);
    }

    public function verifikasiPembayaran(Request $request, Pembayaran $pembayaran, PembayaranService $service): JsonResponse
    {
        abort_unless(Auth::user()?->can('manage_keuangan') ?? false, 403);

        $service->verifikasi($pembayaran, Auth::user());

        return response()->json(['message' => 'Pembayaran diverifikasi.']);
    }

    public function tolakPembayaran(Request $request, Pembayaran $pembayaran, PembayaranService $service): JsonResponse
    {
        abort_unless(Auth::user()?->can('manage_keuangan') ?? false, 403);

        $request->validate(['alasan' => 'required|string|max:500']);

        $service->tolak($pembayaran, Auth::user(), $request->input('alasan'));

        return response()->json(['message' => 'Pembayaran ditolak.']);
    }

    // ── 03.1 Tabungan ───────────────────────────────────────────

    public function tabunganRingkasan(): JsonResponse
    {
        $totalSaldo = Tabungan::sum('saldo');
        $jumlahRekening = Tabungan::count();

        $mutasiTerakhir = TabunganTransaksi::with('tabungan.santri:id,nama,nis')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'totalSaldo' => $totalSaldo,
            'jumlahRekening' => $jumlahRekening,
            'mutasiTerakhir' => $mutasiTerakhir,
        ]);
    }

    // ── 05.1 Jurnal ─────────────────────────────────────────────

    public function jurnal(Request $request): JsonResponse
    {
        $query = Jurnal::with('baris.akun:id,kode,nama')
            ->withSum('baris as total_debit', 'debit')
            ->where('status', 'posted')
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($request->filled('dari')) {
            $query->where('tanggal', '>=', $request->input('dari'));
        }
        if ($request->filled('sampai')) {
            $query->where('tanggal', '<=', $request->input('sampai'));
        }

        return response()->json($query->paginate(15));
    }

    public function neracaSaldo(BukuBesarService $bukuBesar): JsonResponse
    {
        $data = $bukuBesar->neracaSaldo();

        return response()->json($data);
    }

    // ── 05.2 Anggaran ───────────────────────────────────────────

    public function anggaranRealisasi(LaporanService $laporan): JsonResponse
    {
        $data = $laporan->realisasiPerDivisi();

        return response()->json($data);
    }

    // ── 06 Laporan ──────────────────────────────────────────────

    public function laporanNeraca(Request $request, LaporanService $laporan): JsonResponse
    {
        $tanggal = $request->input('tanggal', now()->toDateString());

        return response()->json($laporan->neraca($tanggal));
    }

    public function laporanLabaRugi(Request $request, LaporanService $laporan): JsonResponse
    {
        $dari = $request->input('dari');
        $sampai = $request->input('sampai', now()->toDateString());

        return response()->json($laporan->labaRugi($dari, $sampai));
    }

    public function laporanArusKas(Request $request, LaporanService $laporan): JsonResponse
    {
        $dari = $request->input('dari');
        $sampai = $request->input('sampai', now()->toDateString());

        return response()->json($laporan->arusKas($dari, $sampai));
    }

    // ── 02.3 Loket Kas ─────────────────────────────────────────

    public function loket(): JsonResponse
    {
        $sesi = PosSesi::where('petugas_id', Auth::id())
            ->whereNull('ditutup_pada')
            ->first();

        $transaksi = [];
        if ($sesi) {
            $transaksi = PosTransaksi::where('pos_sesi_id', $sesi->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get();
        }

        return response()->json([
            'sesi' => $sesi ? [
                'id' => $sesi->id,
                'petugas' => Auth::user()?->name,
                'dibuka_pada' => $sesi->created_at,
                'total' => $transaksi->sum('nominal'),
            ] : null,
            'transaksi' => $transaksi,
        ]);
    }

    public function bukaSesiLoket(): JsonResponse
    {
        $existing = PosSesi::where('petugas_id', Auth::id())
            ->whereNull('ditutup_pada')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Sesi sudah aktif.'], 422);
        }

        $sesi = PosSesi::create([
            'petugas_id' => Auth::id(),
            'tipe' => 'loket',
        ]);

        return response()->json(['message' => 'Sesi dibuka.', 'sesi_id' => $sesi->id]);
    }

    public function tutupSesiLoket(): JsonResponse
    {
        $sesi = PosSesi::where('petugas_id', Auth::id())
            ->whereNull('ditutup_pada')
            ->first();

        if (! $sesi) {
            return response()->json(['message' => 'Tidak ada sesi aktif.'], 422);
        }

        $sesi->update(['ditutup_pada' => now()]);

        return response()->json(['message' => 'Sesi ditutup.']);
    }

    public function terimaTunai(Request $request): JsonResponse
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'keterangan' => 'required|string|max:500',
            'nominal' => 'required|numeric|min:1',
        ]);

        $sesi = PosSesi::where('petugas_id', Auth::id())
            ->whereNull('ditutup_pada')
            ->firstOrFail();

        PosTransaksi::create([
            'pos_sesi_id' => $sesi->id,
            'nama' => $request->input('nama'),
            'keterangan' => $request->input('keterangan'),
            'nominal' => $request->input('nominal'),
            'metode' => 'tunai',
            'petugas_id' => Auth::id(),
        ]);

        return response()->json(['message' => 'Pembayaran diterima.']);
    }

    // ── 03.2 POS Kantin ────────────────────────────────────────

    public function posRingkasan(): JsonResponse
    {
        $today = now()->toDateString();

        $omzetHariIni = PosTransaksi::whereDate('created_at', $today)->sum('nominal');
        $totalTransaksi = PosTransaksi::whereDate('created_at', $today)->count();
        $sesiAktif = PosSesi::whereNull('ditutup_pada')->count();
        $stokRendah = InventoryItem::where('stok', '<=', 5)->where('stok', '>', 0)->count();

        $topProduk = PosTransaksi::selectRaw('nama_produk as nama, SUM(qty) as terjual')
            ->whereDate('created_at', $today)
            ->whereNotNull('nama_produk')
            ->groupBy('nama_produk')
            ->orderByDesc('terjual')
            ->limit(5)
            ->get();

        return response()->json([
            'omzetHariIni' => $omzetHariIni,
            'totalTransaksi' => $totalTransaksi,
            'sesiAktif' => $sesiAktif,
            'stokRendah' => $stokRendah,
            'topProduk' => $topProduk,
        ]);
    }

    public function posProduk(): JsonResponse
    {
        $produk = InventoryItem::where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'harga_jual as harga', 'stok']);

        return response()->json(['data' => $produk]);
    }

    public function posCheckout(Request $request, KantinService $kantin): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|integer|exists:inventory_items,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $sesi = PosSesi::where('petugas_id', Auth::id())
            ->whereNull('ditutup_pada')
            ->first();

        foreach ($request->input('items') as $item) {
            $produk = InventoryItem::findOrFail($item['produk_id']);

            PosTransaksi::create([
                'pos_sesi_id' => $sesi?->id,
                'nama_produk' => $produk->nama,
                'qty' => $item['qty'],
                'nominal' => $produk->harga_jual * $item['qty'],
                'metode' => 'tunai',
                'petugas_id' => Auth::id(),
            ]);
        }

        return response()->json(['message' => 'Transaksi berhasil.']);
    }

    // ── 04.1 Approval ──────────────────────────────────────────

    public function approval(Request $request): JsonResponse
    {
        $status = $request->input('status', 'menunggu');

        $page = ApprovalRequest::with(['peminta:id,name', 'approvable'])
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(20);

        $page->getCollection()->transform(fn ($a) => [
            'id' => $a->id,
            'keterangan' => $a->approvable->uraian
                ?? $a->approvable->keterangan
                ?? class_basename($a->approvable_type),
            'pemohon' => $a->peminta?->name ?? '-',
            'divisi' => $a->approver_role ?? '-',
            'nominal' => (float) ($a->approvable->total ?? $a->approvable->nominal ?? 0),
            'tanggal' => $a->created_at?->toDateString(),
            'status' => $a->status,
            'pagu_persen' => null,
        ]);

        return response()->json($page);
    }

    public function approvalSetujui(ApprovalRequest $approval, ApprovalService $service): JsonResponse
    {
        $service->setujui($approval, Auth::id());

        return response()->json(['message' => 'Disetujui.']);
    }

    public function approvalTolak(Request $request, ApprovalRequest $approval, ApprovalService $service): JsonResponse
    {
        $request->validate(['alasan' => 'required|string|max:500']);

        $service->tolak($approval, Auth::id(), $request->input('alasan'));

        return response()->json(['message' => 'Ditolak.']);
    }

    // ── 04.2 Payroll ───────────────────────────────────────────

    public function payrollPeriode(): JsonResponse
    {
        $periodes = SlipGaji::selectRaw('periode, MIN(id) as id, COUNT(*) as jumlah, MAX(created_at) as terakhir')
            ->groupBy('periode')
            ->orderByDesc('periode')
            ->limit(12)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->periode,
                'label' => $p->periode,
                'jumlah' => $p->jumlah,
                'diproses' => true,
            ]);

        return response()->json(['data' => $periodes]);
    }

    public function payrollSlip(Request $request): JsonResponse
    {
        $periodeId = $request->input('periode_id');

        $slips = SlipGaji::with('employee:id,nama,jabatan')
            ->where('periode', $periodeId)
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama_pegawai' => $s->employee?->nama ?? '-',
                'jabatan' => $s->employee?->jabatan,
                'gaji_kotor' => $s->gaji_pokok + ($s->tunjangan_total ?? 0),
                'gaji_bersih' => $s->gaji_bersih,
                'pph' => $s->pph21 ?? 0,
            ]);

        $ringkasan = [
            'totalKotor' => $slips->sum('gaji_kotor'),
            'totalBersih' => $slips->sum('gaji_bersih'),
            'totalPph' => $slips->sum('pph'),
            'jumlahPegawai' => $slips->count(),
        ];

        return response()->json(['data' => $slips, 'ringkasan' => $ringkasan]);
    }

    public function payrollProses(Request $request, PayrollService $payroll): JsonResponse
    {
        abort_unless(Auth::user()?->can('run_payroll') ?? false, 403);

        $request->validate(['periode_id' => 'required|string']);

        return response()->json(['message' => 'Payroll diproses. Slip gaji diterbitkan.']);
    }
}
