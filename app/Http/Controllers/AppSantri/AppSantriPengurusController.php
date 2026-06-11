<?php

declare(strict_types=1);

namespace App\Http\Controllers\AppSantri;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PengurusSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;

/**
 * WP-21 — Mode Pengurus-Santri di App Santri (/app/pengurus/*).
 *
 * GERBANG GANDA per-request (tanpa cron):
 *  1. auth + role santri + punya baris Santri (login NIS+PIN yang sudah live);
 *  2. baris pengurus_santri BERLAKU hari ini (aktif + periode mencakup today)
 *     — masa jabatan habis = 403 otomatis pada request berikutnya.
 *
 * KEPUTUSAN DESAIN:
 *  - Catat pelanggaran SELALU berstatus 'dicatat' (paksaVerifikasiManual=true)
 *    walau Setting wajib_verifikasi=false — pengurus bukan ustadz, wajib
 *    diverifikasi ustadz dulu (anti bully sesama santri).
 *  - Presensi kegiatan HANYA untuk kegiatan ber-kolom qism yang cocok dengan
 *    qism pengurus; kegiatan tanpa qism / qism lain → 403.
 */
class AppSantriPengurusController extends Controller
{
    /** GET /app/pengurus — beranda mode pengurus. */
    public function home(): Response
    {
        [$santri, $penugasan] = $this->gerbang();

        return Inertia::render('AppSantri/Pengurus/Home', [
            'nama' => $santri->nama,
            'penugasan' => $penugasan->map(fn (PengurusSantri $p) => [
                'qism' => $p->qism,
                'label' => $p->label ?? ('Pengurus '.ucfirst($p->qism)),
                'periode_selesai' => $p->periode_selesai?->toDateString(),
            ])->values(),
        ]);
    }

    /** GET /app/pengurus/pelanggaran — form catat pelanggaran. */
    public function formPelanggaran(): Response
    {
        [, $penugasan] = $this->gerbang();

        return Inertia::render('AppSantri/Pengurus/CatatPelanggaran', [
            'qisms' => $penugasan->pluck('qism')->unique()->values(),
            'santriList' => Santri::query()->where('status', 'aktif')
                ->orderBy('nama')->get(['id', 'nama', 'nis'])
                ->map(fn (Santri $s) => ['id' => $s->id, 'nama' => $s->nama, 'nis' => $s->nis]),
            'jenisList' => JenisPelanggaran::query()->where('aktif', true)
                ->orderBy('kategori')->orderBy('nama')
                ->get(['id', 'nama', 'kategori', 'poin']),
        ]);
    }

    /** POST /app/pengurus/pelanggaran — catat via DisiplinService, status PASTI 'dicatat'. */
    public function simpanPelanggaran(Request $request, DisiplinService $disiplin): RedirectResponse
    {
        $this->gerbang();

        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'jenis_pelanggaran_id' => ['required', 'integer'],
            'kronologi' => ['required', 'string', 'max:2000'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ]);

        $terlapor = Santri::query()->findOrFail($data['santri_id']);
        $jenis = JenisPelanggaran::query()->where('aktif', true)->findOrFail($data['jenis_pelanggaran_id']);

        // paksaVerifikasiManual=true → status SELALU 'dicatat' (menunggu ustadz),
        // saklar wajib_verifikasi tenant TIDAK berlaku untuk pencatat pengurus.
        $disiplin->catat($terlapor, $jenis, $request->user(), [
            'kronologi' => $data['kronologi'],
            'lokasi' => $data['lokasi'] ?? null,
        ], paksaVerifikasiManual: true);

        return back()->with('sukses', 'Laporan tercatat — menunggu verifikasi ustadz.');
    }

    /** GET /app/pengurus/kegiatan — daftar kegiatan domain qism pengurus. */
    public function kegiatan(): Response
    {
        [, $penugasan] = $this->gerbang();
        $qisms = $penugasan->pluck('qism')->unique()->values();

        return Inertia::render('AppSantri/Pengurus/PresensiKegiatan', [
            'qisms' => $qisms,
            'kegiatanList' => Kegiatan::query()
                ->whereIn('qism', $qisms->all())
                ->orderByDesc('mulai')
                ->limit(50)
                ->get(['id', 'nama', 'qism', 'mulai', 'status']),
            'santriList' => Santri::query()->where('status', 'aktif')
                ->orderBy('nama')->get(['id', 'nama', 'nis'])
                ->map(fn (Santri $s) => ['id' => $s->id, 'nama' => $s->nama, 'nis' => $s->nis]),
        ]);
    }

    /** POST /app/pengurus/kegiatan/{kegiatan}/presensi — isi presensi (hanya domain qism-nya). */
    public function simpanPresensi(Request $request, int $kegiatan): RedirectResponse
    {
        [, $penugasan] = $this->gerbang();

        $keg = Kegiatan::query()->findOrFail($kegiatan);

        // Kegiatan tanpa qism = BUKAN domain pengurus; qism lain = bukan wewenangnya.
        $qisms = $penugasan->pluck('qism')->unique();
        abort_if($keg->qism === null || ! $qisms->contains($keg->qism), 403, 'Kegiatan di luar domain qism Anda.');

        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'status' => ['required', 'in:hadir,izin,sakit,alpha'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        Santri::query()->findOrFail($data['santri_id']);

        PresensiKegiatan::query()->updateOrCreate(
            ['kegiatan_id' => $keg->id, 'santri_id' => $data['santri_id']],
            ['status' => $data['status'], 'keterangan' => $data['keterangan'] ?? null],
        );

        return back()->with('sukses', 'Presensi tersimpan.');
    }

    // ------------------------------------------------------------------

    /**
     * Gerbang ganda: user role santri ber-baris Santri + penugasan pengurus
     * BERLAKU hari ini. Gagal salah satu → 403.
     *
     * @return array{0: Santri, 1: Collection<int, PengurusSantri>}
     */
    private function gerbang(): array
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->hasRole('santri'), 403);

        $santri = Santri::query()->where('user_id', $user->id)->first();
        abort_if($santri === null, 403);

        $penugasan = PengurusSantri::query()
            ->where('santri_id', $santri->id)
            ->berlaku()
            ->get();

        abort_if($penugasan->isEmpty(), 403, 'Bukan pengurus aktif.');

        return [$santri, $penugasan];
    }
}
