<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Models\Soal;
use Modules\PPDB\Models\UjianAttempt;
use Modules\PPDB\Services\UjianOnlineService;

/**
 * Portal peserta — Ujian Online (/spmb/ujian): daftar paket aktif, kerjakan
 * (countdown dari deadline SERVER), autosave jawaban, submit. Kunci jawaban
 * TIDAK PERNAH dikirim ke client.
 */
class PesertaUjianController extends Controller
{
    public function __construct(private readonly UjianOnlineService $ujian)
    {
    }

    public function index(Request $request): Response
    {
        $calon = $this->calon($request);
        $attempts = UjianAttempt::query()
            ->where('calon_santri_id', $calon->id)
            ->get()
            ->keyBy('paket_soal_id');

        $paket = PaketSoal::query()->where('is_active', true)->withCount('soal')
            ->orderBy('nama')->get()
            ->filter(fn (PaketSoal $p) => $p->soal_count > 0)
            ->map(function (PaketSoal $p) use ($attempts): array {
                $a = $attempts->get($p->id);

                return [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'kategori' => $p->kategori_ujian,
                    'durasi_menit' => $p->durasi_menit,
                    'jumlah_soal' => $p->jumlah_tampil ?: $p->soal_count,
                    'status' => $a?->status,                       // null|berjalan|menunggu_nilai|selesai
                    'nilai_final' => $a?->nilai_final,
                ];
            })->values();

        return Inertia::render('PPDB/Portal/Ujian', ['paket' => $paket]);
    }

    public function mulai(Request $request, int $paket): RedirectResponse
    {
        $calon = $this->calon($request);
        $this->ujian->mulai($calon, PaketSoal::query()->findOrFail($paket));

        return redirect()->route('tenant.spmb.ujian.kerjakan', ['tenant' => tenant('id') ?? $request->route('tenant'), 'paket' => $paket]);
    }

    public function kerjakan(Request $request, int $paket): Response|RedirectResponse
    {
        $calon = $this->calon($request);
        $attempt = $this->attemptMilik($calon, $paket);

        if ($attempt->status !== 'berjalan') {
            return redirect()->route('tenant.spmb.ujian.index', ['tenant' => tenant('id') ?? $request->route('tenant')]);
        }

        $soal = Soal::query()->whereIn('id', (array) $attempt->soal_tampil)->get()->keyBy('id');

        return Inertia::render('PPDB/Portal/UjianKerjakan', [
            'attemptId' => $attempt->id,
            'paketId' => $paket,
            'paketNama' => $attempt->paket->nama,
            'deadline' => $attempt->deadline_at->toIso8601String(), // countdown client dari waktu SERVER
            'jawaban' => (object) ((array) $attempt->jawaban),
            'soal' => collect((array) $attempt->soal_tampil)->map(function ($id, $i) use ($soal) {
                $s = $soal->get($id);

                return $s ? [
                    'id' => $s->id,
                    'nomor' => $i + 1,
                    'tipe' => $s->tipe,
                    'pertanyaan' => $s->pertanyaan,
                    'pilihan' => $s->tipe === 'pg' ? $s->pilihan : null,
                    // KUNCI sengaja tidak dikirim.
                ] : null;
            })->filter()->values(),
        ]);
    }

    public function jawab(Request $request, int $paket): \Illuminate\Http\JsonResponse
    {
        $calon = $this->calon($request);
        $data = $request->validate([
            'soal_id' => ['required', 'integer'],
            'jawaban' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->ujian->jawab($this->attemptMilik($calon, $paket), (int) $data['soal_id'], $data['jawaban']);

            return response()->json(['ok' => true]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function selesai(Request $request, int $paket): RedirectResponse
    {
        $calon = $this->calon($request);
        $this->ujian->selesaikan($this->attemptMilik($calon, $paket));

        return redirect()
            ->route('tenant.spmb.ujian.index', ['tenant' => tenant('id') ?? $request->route('tenant')])
            ->with('success', 'Jawaban terkirim. Terima kasih — hasil akan diumumkan panitia.');
    }

    /** Attempt MILIK peserta login (kepemilikan = isolasi). */
    private function attemptMilik(CalonSantri $calon, int $paketId): UjianAttempt
    {
        return UjianAttempt::query()
            ->where('calon_santri_id', $calon->id)
            ->where('paket_soal_id', $paketId)
            ->firstOrFail();
    }

    private function calon(Request $request): CalonSantri
    {
        return CalonSantri::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
