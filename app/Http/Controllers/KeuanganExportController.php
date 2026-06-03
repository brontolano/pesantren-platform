<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KeuanganExportController extends Controller
{
    // ── CSV: Laporan Tagihan ──────────────────────────────

    public function tagihanCsv(Request $request): StreamedResponse
    {
        $this->authorizeExport();

        $query = $this->buildTagihanQuery($request);
        $tagihan = $query->get();

        $filename = 'laporan-tagihan-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tagihan): void {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'NIS',
                'Nama Santri',
                'Jenis Tagihan',
                'Keterangan',
                'Nominal',
                'Diskon',
                'Nominal Akhir',
                'Total Dibayar',
                'Sisa',
                'Status',
                'Jatuh Tempo',
                'Periode',
                'Bulan Masehi',
                'Bulan Hijriah',
            ]);

            foreach ($tagihan as $item) {
                fputcsv($handle, [
                    optional($item->santri)->nis,
                    optional($item->santri)->nama,
                    $item->jenis_tagihan,
                    $item->keterangan,
                    $item->nominal,
                    $item->diskon,
                    $item->nominal_akhir,
                    $item->total_dibayar,
                    $item->sisa_tagihan,
                    $item->status,
                    optional($item->jatuh_tempo)?->format('Y-m-d'),
                    $item->periode_akademik,
                    $item->bulan_masehi,
                    $item->bulan_hijriah,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── CSV: Laporan Pembayaran ───────────────────────────

    public function pembayaranCsv(Request $request): StreamedResponse
    {
        $this->authorizeExport();

        $query = $this->buildPembayaranQuery($request);
        $pembayaran = $query->get();

        $filename = 'laporan-pembayaran-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($pembayaran): void {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Tanggal Bayar',
                'NIS',
                'Nama Santri',
                'Jenis Tagihan',
                'Nominal Bayar',
                'Metode',
                'No. Bukti',
                'Verified',
                'Verified By',
                'Verified At',
            ]);

            foreach ($pembayaran as $item) {
                fputcsv($handle, [
                    optional($item->tanggal_pembayaran)?->format('Y-m-d'),
                    optional($item->santri)->nis,
                    optional($item->santri)->nama,
                    optional($item->tagihan)->jenis_tagihan,
                    $item->nominal,
                    $item->metode,
                    $item->nomor_bukti,
                    $item->is_verified ? 'Ya' : 'Belum',
                    optional($item->verifier)->name,
                    optional($item->verified_at)?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Print: Laporan Tagihan ────────────────────────────

    public function tagihanPrint(Request $request)
    {
        $this->authorizeExport();

        $query = $this->buildTagihanQuery($request);
        $tagihan = $query->get();

        $totalNominal = $tagihan->sum('nominal_akhir');
        $totalDibayar = $tagihan->sum(fn ($t) => $t->total_dibayar);
        $totalPiutang = $totalNominal - $totalDibayar;

        return response()->view('exports.laporan-keuangan-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'title' => 'Laporan Tagihan',
            'tagihan' => $tagihan,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'jenis' => $request->string('jenis_tagihan')->toString(),
                'periode' => $request->string('periode_akademik')->toString(),
                'dari' => $request->string('dari')->toString(),
                'sampai' => $request->string('sampai')->toString(),
            ],
            'summary' => [
                'total_tagihan' => $totalNominal,
                'total_dibayar' => $totalDibayar,
                'total_piutang' => $totalPiutang,
            ],
            'printedAt' => now(),
        ]);
    }

    // ── Print: Laporan Pembayaran ─────────────────────────

    public function pembayaranPrint(Request $request)
    {
        $this->authorizeExport();

        $query = $this->buildPembayaranQuery($request);
        $pembayaran = $query->get();

        $totalNominal = $pembayaran->sum('nominal');

        return response()->view('exports.laporan-pembayaran-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'title' => 'Laporan Pembayaran',
            'pembayaran' => $pembayaran,
            'filters' => [
                'dari' => $request->string('dari')->toString(),
                'sampai' => $request->string('sampai')->toString(),
                'metode' => $request->string('metode')->toString(),
                'verified' => $request->string('verified')->toString(),
            ],
            'totalNominal' => $totalNominal,
            'printedAt' => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────

    private function authorizeExport(): void
    {
        abort_unless(auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']), 403);
    }

    private function buildTagihanQuery(Request $request)
    {
        return Tagihan::query()
            ->with('santri')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('jenis_tagihan'), fn ($q) => $q->where('jenis_tagihan', $request->input('jenis_tagihan')))
            ->when($request->filled('periode_akademik'), fn ($q) => $q->where('periode_akademik', $request->input('periode_akademik')))
            ->when($request->filled('dari'), fn ($q) => $q->where('jatuh_tempo', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->where('jatuh_tempo', '<=', $request->input('sampai')))
            ->orderBy('jatuh_tempo', 'desc');
    }

    private function buildPembayaranQuery(Request $request)
    {
        return Pembayaran::query()
            ->with(['santri', 'tagihan', 'verifier'])
            ->when($request->filled('dari'), fn ($q) => $q->where('tanggal_pembayaran', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->where('tanggal_pembayaran', '<=', $request->input('sampai')))
            ->when($request->filled('metode'), fn ($q) => $q->where('metode', $request->input('metode')))
            ->when($request->input('verified') === '1', fn ($q) => $q->where('is_verified', true))
            ->when($request->input('verified') === '0', fn ($q) => $q->where('is_verified', false))
            ->orderBy('tanggal_pembayaran', 'desc');
    }
}
