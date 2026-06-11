<?php

declare(strict_types=1);

namespace App\Http\Controllers\AppSantri;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\SesiUjianAkademik;
use Modules\Akademik\Services\UjianAkademikService;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\Soal;
use Modules\PPDB\Models\UjianAttempt;

/**
 * F4-PWA — Ujian di App Santri (/app/ujian): daftar sesi aktif kelas →
 * kerjakan (timer server-side, REUSE mekanisme attempt PPDB) → submit.
 * Satu attempt per santri per sesi (unique partial + guard service).
 */
class AppSantriUjianController extends Controller
{
    public function __construct(private readonly UjianAkademikService $ujian) {}

    public function index(): Response|RedirectResponse
    {
        $santri = $this->santriSaatIni();
        if ($santri === null) {
            return redirect()->route('tenant.app-santri.login', ['tenant' => tenant('id')]);
        }

        $attempts = UjianAttempt::query()
            ->where('santri_id', $santri->id)
            ->whereNotNull('sesi_ujian_akademik_id')
            ->get()
            ->keyBy('sesi_ujian_akademik_id');

        $sesi = SesiUjianAkademik::query()
            ->with(['paket', 'pelajaran'])
            ->where('kelas_id', $santri->kelas_id)
            ->where('is_active', true)
            ->orderBy('mulai_at')
            ->get()
            ->map(function (SesiUjianAkademik $s) use ($attempts): array {
                $attempt = $attempts->get($s->id);

                return [
                    'id' => $s->id,
                    'nama' => $s->nama ?: $s->paket?->nama,
                    'pelajaran' => $s->pelajaran?->nama,
                    'mulai_at' => $s->mulai_at?->format('d M Y H:i'),
                    'selesai_at' => $s->selesai_at?->format('d M Y H:i'),
                    'berlangsung' => $s->sedangBerlangsung(),
                    'durasi_menit' => $s->paket?->durasi_menit,
                    'status_attempt' => $attempt?->status,
                    'nilai_final' => $attempt?->nilai_final,
                ];
            })->values();

        return Inertia::render('AppSantri/UjianList', [
            'nama' => $santri->nama,
            'sesi' => $sesi,
        ]);
    }

    public function mulai(SesiUjianAkademik $sesi): RedirectResponse
    {
        $santri = $this->santriSaatIni();
        abort_if($santri === null, 403);

        try {
            $this->ujian->mulai($santri, $sesi);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('tenant.app-santri.ujian.index', ['tenant' => tenant('id')])
                ->withErrors(['sesi' => $e->getMessage()]);
        }

        return redirect()->route('tenant.app-santri.ujian.kerjakan', ['tenant' => tenant('id'), 'sesi' => $sesi->id]);
    }

    public function kerjakan(SesiUjianAkademik $sesi): Response|RedirectResponse
    {
        $santri = $this->santriSaatIni();
        abort_if($santri === null, 403);

        $attempt = $this->attemptKu($santri, $sesi);
        if ($attempt === null) {
            return redirect()->route('tenant.app-santri.ujian.index', ['tenant' => tenant('id')]);
        }

        $soal = Soal::query()
            ->whereIn('id', (array) $attempt->soal_tampil)
            ->get()
            ->sortBy(fn (Soal $s) => array_search($s->id, (array) $attempt->soal_tampil, true))
            ->map(fn (Soal $s): array => [
                'id' => $s->id,
                'tipe' => $s->tipe,
                'pertanyaan' => $s->pertanyaan,
                'pilihan' => $s->tipe === 'pg' ? $s->pilihan : null,
                // KUNCI TIDAK PERNAH dikirim ke klien.
            ])->values();

        return Inertia::render('AppSantri/UjianKerjakan', [
            'sesi' => ['id' => $sesi->id, 'nama' => $sesi->nama ?: $sesi->paket?->nama],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'deadline_at' => $attempt->deadline_at?->toIso8601String(),
                'sisa_detik' => max(0, (int) now()->diffInSeconds($attempt->deadline_at, false)),
                'jawaban' => (object) ((array) $attempt->jawaban),
                'nilai_final' => $attempt->nilai_final,
            ],
            'soal' => $soal,
        ]);
    }

    public function jawab(Request $request, SesiUjianAkademik $sesi): JsonResponse
    {
        $santri = $this->santriSaatIni();
        abort_if($santri === null, 403);

        $data = $request->validate([
            'soal_id' => ['required', 'integer'],
            'jawaban' => ['required', 'string', 'max:10000'],
        ]);

        $attempt = $this->attemptKu($santri, $sesi);
        abort_if($attempt === null, 404);

        try {
            $this->ujian->jawab($attempt, (int) $data['soal_id'], $data['jawaban']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function selesai(SesiUjianAkademik $sesi): RedirectResponse
    {
        $santri = $this->santriSaatIni();
        abort_if($santri === null, 403);

        $attempt = $this->attemptKu($santri, $sesi);
        abort_if($attempt === null, 404);

        $this->ujian->selesaikan($attempt);

        return redirect()->route('tenant.app-santri.ujian.index', ['tenant' => tenant('id')])
            ->with('sukses', 'Ujian selesai dikumpulkan.');
    }

    private function santriSaatIni(): ?Santri
    {
        if (! Auth::user()?->hasRole('santri')) {
            return null;
        }

        return Santri::query()->where('user_id', Auth::id())->first();
    }

    /** Attempt MILIK santri ini pada sesi ini saja — bukan attempt orang lain. */
    private function attemptKu(Santri $santri, SesiUjianAkademik $sesi): ?UjianAttempt
    {
        return UjianAttempt::query()
            ->where('santri_id', $santri->id)
            ->where('sesi_ujian_akademik_id', $sesi->id)
            ->first();
    }
}
