<?php

namespace Modules\Kegiatan\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\LogGerbang;
use Modules\Kesantrian\Models\Santri;
use Modules\Notifikasi\Services\NotifDispatcher;
use RuntimeException;
use Throwable;

/**
 * WP-29 — Pos Gerbang: status izin santri hari ini + catat keluar/masuk.
 * Sinkron izin pulang WP-26: arah masuk dengan izin aktif → tandaiKembali
 * (HANYA memanggil IzinSantriService, tidak mengubah logikanya).
 */
class GerbangService
{
    public function __construct(private readonly IzinSantriService $izinService) {}

    /**
     * Status gerbang santri hari ini.
     *
     * @return array{status:string, izin:?IzinSantri}
     *                                                status: hijau (izin disetujui aktif hari ini, belum kembali),
     *                                                kuning (terlambat: tanggal_selesai < hari ini, belum kembali),
     *                                                merah (tanpa izin aktif).
     */
    public function statusSantri(Santri $santri): array
    {
        $this->ensureTenantContext();
        $today = Carbon::today();

        $izinAktif = IzinSantri::query()
            ->where('santri_id', $santri->id)
            ->where('status', 'disetujui')
            ->where('sudah_kembali', false)
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->orderBy('tanggal_mulai')
            ->first();

        if ($izinAktif !== null) {
            return ['status' => 'hijau', 'izin' => $izinAktif];
        }

        $terlambat = IzinSantri::query()
            ->where('santri_id', $santri->id)
            ->where('status', 'disetujui')
            ->where('sudah_kembali', false)
            ->whereDate('tanggal_selesai', '<', $today)
            ->orderByDesc('tanggal_selesai')
            ->first();

        if ($terlambat !== null) {
            return ['status' => 'kuning', 'izin' => $terlambat];
        }

        return ['status' => 'merah', 'izin' => null];
    }

    /**
     * Catat lewat gerbang. Arah masuk + ada izin aktif → tandai kembali
     * (sinkron WP-26). Keluar tanpa izin → is_tanpa_izin + notif pengasuhan
     * (fire-and-forget, NotifDispatcher tak pernah melempar).
     */
    public function catat(Santri $santri, string $arah, User $petugas, ?string $catatan = null): LogGerbang
    {
        $this->ensureTenantContext();

        if (! in_array($arah, ['keluar', 'masuk'], true)) {
            throw new InvalidArgumentException("Arah gerbang tidak valid: {$arah}.");
        }

        $kondisi = $this->statusSantri($santri);
        $izin = $kondisi['izin'];
        $tanpaIzin = $arah === 'keluar' && $kondisi['status'] === 'merah';

        // Anti-spam: scan berulang santri yang sama dalam 10 menit → satu notif saja.
        $sudahDinotif = $tanpaIzin && LogGerbang::query()
            ->where('santri_id', $santri->id)
            ->where('is_tanpa_izin', true)
            ->where('waktu', '>=', now()->subMinutes(10))
            ->exists();

        $log = DB::transaction(fn (): LogGerbang => LogGerbang::create([
            'tenant_id' => tenant('id'),
            'santri_id' => $santri->id,
            'arah' => $arah,
            'waktu' => now(),
            'petugas_id' => $petugas->id,
            'izin_id' => $izin?->id,
            'is_tanpa_izin' => $tanpaIzin,
            'catatan' => $catatan,
        ]));

        // Masuk dengan izin (aktif ATAU terlambat) → tandai sudah kembali.
        if ($arah === 'masuk' && $izin !== null && ! $izin->sudah_kembali) {
            $this->izinService->tandaiKembali($izin);
        }

        if ($tanpaIzin && ! $sudahDinotif) {
            try {
                app(NotifDispatcher::class)->kirim('gerbang_tanpa_izin', [
                    'data' => [
                        'nama_santri' => $santri->nama,
                        'nis' => (string) $santri->nis,
                        'waktu' => now()->format('d-m-Y H:i'),
                        'petugas' => $petugas->name,
                    ],
                ]);
            } catch (Throwable $e) {
                Log::warning('Notif gerbang tanpa izin gagal', ['error' => $e->getMessage()]);
            }
        }

        return $log;
    }

    /** Daftar izin aktif hari ini (cache offline ringan layar pos). */
    public function izinAktifHariIni(): array
    {
        $this->ensureTenantContext();
        $today = Carbon::today();

        return IzinSantri::query()
            ->with('santri:id,nis,nama')
            ->where('status', 'disetujui')
            ->where('sudah_kembali', false)
            ->whereDate('tanggal_mulai', '<=', $today)
            ->get()
            ->map(fn (IzinSantri $i): array => [
                'izin_id' => $i->id,
                'nis' => (string) ($i->santri?->nis ?? ''),
                'nama' => $i->santri?->nama,
                'jenis_izin' => $i->jenis_izin,
                'tanggal_mulai' => $i->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $i->tanggal_selesai->toDateString(),
                'terlambat' => $i->tanggal_selesai->lt($today),
            ])->values()->all();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
