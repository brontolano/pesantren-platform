<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kegiatan\Models\LogGerbang;
use Modules\Kegiatan\Models\PengajuanJenguk;
use Modules\Kegiatan\Services\GerbangService;
use Modules\Kegiatan\Services\PenjengukanService;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-29/30 — Layar Pos Gerbang PWA Guru (mode keamanan, menumpang /guru).
 * Scan QR berisi NIS (pola keyboard-wedge: input autofocus, scanner
 * mengetik NIS + Enter) → kartu santri + status izin → catat keluar/masuk.
 * Satu layar yang sama melayani registrasi tamu penjengukan (WP-30).
 */
class PortalGuruGerbangController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(
        private readonly GerbangService $gerbang,
        private readonly PenjengukanService $penjengukan,
    ) {}

    public function index(Request $request): Response
    {
        $nis = trim((string) $request->query('nis', ''));
        $santriCard = null;
        $jengukHariIni = [];

        if ($nis !== '') {
            // Hanya santri AKTIF yang bisa discan — kartu alumni/keluar tak berlaku.
            $santri = Santri::query()->where('nis', $nis)->where('status', 'aktif')->first();

            if ($santri !== null) {
                $kondisi = $this->gerbang->statusSantri($santri);
                $izin = $kondisi['izin'];

                $santriCard = [
                    'id' => $santri->id,
                    'nis' => (string) $santri->nis,
                    'nama' => $santri->nama,
                    'kelas' => $santri->kelas?->nama ?? '-',
                    'foto' => $santri->photo_url
                        ? url('/_tenant-storage/'.ltrim($santri->photo_url, '/'))
                        : null,
                    'status' => $kondisi['status'], // hijau | kuning | merah
                    'izin' => $izin === null ? null : [
                        'id' => $izin->id,
                        'jenis_izin' => $izin->jenis_izin,
                        'tanggal_mulai' => $izin->tanggal_mulai->format('d-m-Y'),
                        'tanggal_selesai' => $izin->tanggal_selesai->format('d-m-Y'),
                    ],
                ];

                // WP-30: pengajuan jenguk dikonfirmasi hari ini untuk santri ini.
                $jengukHariIni = PengajuanJenguk::query()
                    ->with('pengaju:id,name')
                    ->where('santri_id', $santri->id)
                    ->whereDate('tanggal', Carbon::today())
                    ->where('status', 'dikonfirmasi')
                    ->get()
                    ->map(fn (PengajuanJenguk $p): array => [
                        'id' => $p->id,
                        'pengaju' => $p->pengaju?->name,
                        'catatan' => $p->catatan,
                    ])->values()->all();
            } else {
                $santriCard = ['tidak_ditemukan' => true, 'nis' => $nis];
            }
        }

        return Inertia::render('PortalGuru/Gerbang', [
            ...$this->sharedProps($request),
            'nis' => $nis,
            'santriCard' => $santriCard,
            'jengukHariIni' => $jengukHariIni,
            'walkInBoleh' => $this->penjengukan->jadwal()['walk_in_boleh'],
            'logTerbaru' => $this->logTerbaru(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function logTerbaru(): array
    {
        return LogGerbang::query()
            ->with('santri:id,nis,nama')
            ->whereDate('waktu', Carbon::today())
            ->orderByDesc('waktu')
            ->limit(15)
            ->get()
            ->map(fn ($log): array => [
                'id' => $log->id,
                'nama' => $log->santri?->nama,
                'arah' => $log->arah,
                'waktu' => $log->waktu->format('H:i'),
                'is_tanpa_izin' => $log->is_tanpa_izin,
            ])->values()->all();
    }

    public function catat(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'arah' => ['required', 'in:keluar,masuk'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $santri = Santri::findOrFail($data['santri_id']);
        $log = $this->gerbang->catat($santri, $data['arah'], $this->currentGuru($request), $data['catatan'] ?? null);

        $pesan = $log->is_tanpa_izin
            ? 'Dicatat KELUAR TANPA IZIN — pengasuhan dinotifikasi.'
            : "Dicatat: {$santri->nama} {$data['arah']}.";

        return back()->with('success', $pesan);
    }

    /** GET /guru/gerbang/izin-aktif — cache offline ringan layar pos. */
    public function izinAktif(): JsonResponse
    {
        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'izin' => $this->gerbang->izinAktifHariIni(),
        ]);
    }

    /** WP-30 — tandai hadir pengajuan jenguk dikonfirmasi hari ini. */
    public function jengukHadir(Request $request, int $pengajuan): RedirectResponse
    {
        $data = $request->validate([
            'nama_pengunjung' => ['nullable', 'string', 'max:255'],
            'hubungan' => ['nullable', 'string', 'max:50'],
        ]);

        $row = PengajuanJenguk::findOrFail($pengajuan);
        $this->penjengukan->tandaiHadir($row, $data['nama_pengunjung'] ?? null, $data['hubungan'] ?? null);

        return back()->with('success', 'Tamu ditandai hadir & kunjungan tercatat.');
    }

    /** WP-30 — registrasi tamu walk-in (tanpa pengajuan). */
    public function jengukWalkIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'nama_pengunjung' => ['required', 'string', 'max:255'],
            'hubungan' => ['required', 'string', 'max:50'],
            'keperluan' => ['nullable', 'string', 'max:500'],
        ]);

        $santri = Santri::findOrFail($data['santri_id']);
        $this->penjengukan->walkIn($santri, $data['nama_pengunjung'], $data['hubungan'], $data['keperluan'] ?? null);

        return back()->with('success', 'Kunjungan walk-in tercatat.');
    }
}
