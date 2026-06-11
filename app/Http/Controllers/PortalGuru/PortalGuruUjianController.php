<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\SesiUjianAkademik;
use Modules\Akademik\Services\NilaiService;
use Modules\Akademik\Services\UjianAkademikService;
use Modules\PPDB\Models\Soal;
use Modules\PPDB\Models\UjianAttempt;

/**
 * F4-PWA — Ujian di PWA Guru: daftar sesi (pelajaran ampuan), mode
 * pengawas (daftar attempt live via polling), antrean koreksi essay, dan
 * aksi "Tarik ke Nilai" per sesi (komponen dipilih saat tarik).
 */
class PortalGuruUjianController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(private readonly UjianAkademikService $ujian) {}

    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);

        $sesi = $this->sesiUntuk($guru)
            ->map(fn (SesiUjianAkademik $s): array => [
                'id' => $s->id,
                'nama' => $s->nama ?: ($s->paket?->nama.' — '.$s->kelas?->nama_kelas),
                'pelajaran' => $s->pelajaran?->nama,
                'kelas' => $s->kelas?->nama_kelas,
                'mulai_at' => $s->mulai_at?->format('d M Y H:i'),
                'selesai_at' => $s->selesai_at?->format('d M Y H:i'),
                'berlangsung' => $s->sedangBerlangsung(),
                'jumlah_attempt' => $s->attempts()->count(),
                'jumlah_selesai' => $s->attempts()->where('status', 'selesai')->count(),
                'komponen' => app(NilaiService::class)
                    ->komponenUntukPelajaran((int) $s->pelajaran_id)
                    ->map(fn ($k): array => ['id' => $k->id, 'nama' => $k->nama])->values(),
            ])->values();

        return Inertia::render('PortalGuru/UjianIndex', [
            ...$this->sharedProps($request),
            'sesi' => $sesi,
        ]);
    }

    /** Mode pengawas: layar + data attempt live (polling JSON). */
    public function pengawas(Request $request, SesiUjianAkademik $sesi): Response
    {
        $this->pastikanSesiMilikku($this->currentGuru($request), $sesi);

        return Inertia::render('PortalGuru/UjianPengawas', [
            ...$this->sharedProps($request),
            'sesi' => [
                'id' => $sesi->id,
                'nama' => $sesi->nama ?: $sesi->paket?->nama,
                'kelas' => $sesi->kelas?->nama_kelas,
                'pelajaran' => $sesi->pelajaran?->nama,
                'selesai_at' => $sesi->selesai_at?->toIso8601String(),
            ],
            'attempts' => $this->attemptsLive($sesi),
        ]);
    }

    public function pengawasData(Request $request, SesiUjianAkademik $sesi): JsonResponse
    {
        $this->pastikanSesiMilikku($this->currentGuru($request), $sesi);

        return response()->json(['attempts' => $this->attemptsLive($sesi)]);
    }

    /** Antrean koreksi essay: attempt menunggu_nilai pada sesi ampuanku. */
    public function koreksi(Request $request): Response
    {
        $guru = $this->currentGuru($request);
        $sesiIds = $this->sesiUntuk($guru)->pluck('id');

        $antrean = UjianAttempt::query()
            ->with(['santri', 'sesiAkademik.pelajaran'])
            ->whereIn('sesi_ujian_akademik_id', $sesiIds)
            ->where('status', 'menunggu_nilai')
            ->orderBy('selesai_at')
            ->get()
            ->map(function (UjianAttempt $a): array {
                $soalEssay = Soal::query()
                    ->whereIn('id', (array) $a->soal_tampil)
                    ->where('tipe', '!=', 'pg')
                    ->get();
                $jawaban = (array) $a->jawaban;

                return [
                    'attempt_id' => $a->id,
                    'santri' => $a->santri?->nama,
                    'nis' => $a->santri?->nis,
                    'pelajaran' => $a->sesiAkademik?->pelajaran?->nama,
                    'sesi' => $a->sesiAkademik?->nama ?: $a->sesiAkademik?->paket?->nama,
                    'nilai_pg' => $a->nilai_pg,
                    'essay' => $soalEssay->map(fn (Soal $s): array => [
                        'soal_id' => $s->id,
                        'pertanyaan' => $s->pertanyaan,
                        'bobot' => $s->bobot,
                        'jawaban' => $jawaban[(string) $s->id] ?? null,
                    ])->values(),
                ];
            })->values();

        return Inertia::render('PortalGuru/UjianKoreksi', [
            ...$this->sharedProps($request),
            'antrean' => $antrean,
        ]);
    }

    /** Simpan nilai essay satu attempt → UjianAkademikService (final tertimbang). */
    public function koreksiSimpan(Request $request, UjianAttempt $attempt): JsonResponse
    {
        $data = $request->validate([
            'skor' => ['required', 'array'],
            'skor.*' => ['numeric', 'min:0'],
        ]);

        try {
            $hasil = $this->ujian->nilaiEssay($this->currentGuru($request), $attempt, $data['skor']);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'nilai_final' => $hasil->nilai_final]);
    }

    /** Tarik nilai final sesi → komponen nilai terpilih (idempoten). */
    public function tarikNilai(Request $request, SesiUjianAkademik $sesi): RedirectResponse
    {
        $data = $request->validate(['komponen_id' => ['required', 'integer']]);

        // Komponen harus anggota daftar komponen pelajaran sesi — sumber
        // yang sama dengan picker di UjianIndex (NilaiService).
        $valid = app(NilaiService::class)
            ->komponenUntukPelajaran((int) $sesi->pelajaran_id)
            ->pluck('id')
            ->contains((int) $data['komponen_id']);
        abort_unless($valid, 422, 'Komponen nilai tidak valid untuk pelajaran sesi ini.');

        try {
            $jumlah = $this->ujian->tarikKeNilai($sesi, (int) $data['komponen_id'], $this->currentGuru($request));
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['komponen_id' => $e->getMessage()]);
        }

        return back()->with('sukses', "Nilai {$jumlah} santri ditarik ke komponen.");
    }

    /** @return Collection<int, SesiUjianAkademik> */
    private function sesiUntuk(User $guru)
    {
        $query = SesiUjianAkademik::query()
            ->with(['paket', 'pelajaran', 'kelas'])
            ->orderByDesc('mulai_at');

        if (! $guru->can('manage_akademik')) {
            $query->whereHas('pelajaran', fn ($q) => $q->where('guru_pengampu_id', $guru->id));
        }

        return $query->get();
    }

    private function pastikanSesiMilikku(User $guru, SesiUjianAkademik $sesi): void
    {
        if ($guru->can('manage_akademik')) {
            return;
        }

        abort_unless(
            (int) ($sesi->pelajaran?->guru_pengampu_id) === (int) $guru->id,
            403,
            'Sesi ini bukan pelajaran ampuan Anda.',
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function attemptsLive(SesiUjianAkademik $sesi): array
    {
        // Sweep: finalisasi attempt 'berjalan' yang deadline-nya lewat agar
        // pengawas melihat status & nilai final, bukan attempt menggantung.
        $this->ujian->finalisasiKedaluwarsa($sesi);

        return $sesi->attempts()
            ->with('santri')
            ->orderBy('mulai_at')
            ->get()
            ->map(fn (UjianAttempt $a): array => [
                'id' => $a->id,
                'santri' => $a->santri?->nama,
                'nis' => $a->santri?->nis,
                'status' => $a->status,
                'mulai_at' => $a->mulai_at?->format('H:i'),
                'selesai_at' => $a->selesai_at?->format('H:i'),
                'sisa_detik' => $a->status === 'berjalan'
                    ? max(0, (int) now()->diffInSeconds($a->deadline_at, false))
                    : 0,
                'terjawab' => count((array) $a->jawaban),
                'total_soal' => count((array) $a->soal_tampil),
            ])->values()->all();
    }
}
