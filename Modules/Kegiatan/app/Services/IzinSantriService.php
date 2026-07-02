<?php

namespace Modules\Kegiatan\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

class IzinSantriService
{
    /**
     * Ajukan izin pulang — validasi kuota, overlap, H-minimum sebelum membuat record.
     *
     * @param array{santri_id:int, jenis_izin:string, tanggal_mulai:string, tanggal_selesai:string,
     *     alasan:string, client_ref?:string} $payload
     */
    public function ajukan(array $payload): IzinSantri
    {
        $this->ensureTenantContext();

        $santri = Santri::findOrFail($payload['santri_id']);
        $tglMulai = Carbon::parse($payload['tanggal_mulai'])->startOfDay();
        $tglSelesai = Carbon::parse($payload['tanggal_selesai'])->startOfDay();

        return DB::transaction(function () use ($payload, $tglMulai, $tglSelesai, $santri) {
            // Check idempoten dulu sebelum validasi (agar client_ref yang sama bisa di-resend tanpa error)
            if (! empty($payload['client_ref'])) {
                $existing = IzinSantri::where('tenant_id', tenant('id'))
                    ->where('santri_id', $payload['santri_id'])
                    ->where('client_ref', $payload['client_ref'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $this->validate($payload, $tglMulai, $santri);

            return IzinSantri::create([
                'tenant_id' => tenant('id'),
                'santri_id' => $payload['santri_id'],
                'jenis_izin' => $payload['jenis_izin'],
                'tanggal_mulai' => $tglMulai->toDateString(),
                'tanggal_selesai' => $tglSelesai->toDateString(),
                'alasan' => $payload['alasan'],
                'status' => 'diajukan',
                'client_ref' => $payload['client_ref'] ?? null,
            ]);
        });
    }

    /**
     * Setujui izin — validasi status diajukan, update status → disetujui, catat petugas.
     */
    public function setujui(IzinSantri $izin, User $user): IzinSantri
    {
        $this->ensureTenantContext();

        if ($izin->status !== 'diajukan') {
            throw new InvalidArgumentException("Izin harus status 'diajukan', sekarang {$izin->status}.");
        }

        $izin->update([
            'status' => 'disetujui',
            'disetujui_oleh' => $user->id,
        ]);

        return $izin->refresh();
    }

    /**
     * Tolak izin — update status → ditolak, catat alasan.
     */
    public function tolak(IzinSantri $izin, string $alasan, User $user): IzinSantri
    {
        $this->ensureTenantContext();

        if ($izin->status !== 'diajukan') {
            throw new InvalidArgumentException("Izin harus status 'diajukan', sekarang {$izin->status}.");
        }

        $izin->update([
            'status' => 'ditolak',
            'catatan_pengurus' => $alasan,
            'disetujui_oleh' => $user->id,
        ]);

        return $izin->refresh();
    }

    /**
     * Tandai sudah kembali — update sudah_kembali = true, catat tanggal_kembali_aktual.
     */
    public function tandaiKembali(IzinSantri $izin): IzinSantri
    {
        $this->ensureTenantContext();

        if ($izin->status !== 'disetujui') {
            throw new InvalidArgumentException("Hanya izin 'disetujui' yang bisa ditandai kembali, sekarang {$izin->status}.");
        }

        if ($izin->sudah_kembali) {
            return $izin->refresh();
        }

        $izin->update([
            'sudah_kembali' => true,
            'tanggal_kembali_aktual' => now(),
        ]);

        return $izin->refresh();
    }

    private function validate(array $payload, Carbon $tglMulai, Santri $santri): void
    {
        if (empty($payload['jenis_izin'])) {
            throw new InvalidArgumentException('jenis_izin wajib diisi.');
        }

        if (! in_array($payload['jenis_izin'], ['pulang', 'sakit', 'keluar_kota', 'kunjungan'], true)) {
            throw new InvalidArgumentException('jenis_izin tidak valid.');
        }

        $tglSelesai = Carbon::parse($payload['tanggal_selesai'])->startOfDay();

        if ($tglSelesai->lessThan($tglMulai)) {
            throw new InvalidArgumentException('tanggal_selesai tidak boleh sebelum tanggal_mulai.');
        }

        // Cek overlap dengan izin diajukan/disetujui lain pada santri yang sama
        $ada = IzinSantri::where('tenant_id', tenant('id'))
            ->where('santri_id', $santri->id)
            ->whereIn('status', ['diajukan', 'disetujui'])
            ->where(function ($q) use ($tglMulai, $tglSelesai) {
                $q->whereBetween('tanggal_mulai', [$tglMulai, $tglSelesai])
                    ->orWhereBetween('tanggal_selesai', [$tglMulai, $tglSelesai])
                    ->orWhere(function ($q2) use ($tglMulai, $tglSelesai) {
                        $q2->where('tanggal_mulai', '<=', $tglMulai)
                            ->where('tanggal_selesai', '>=', $tglSelesai);
                    });
            })
            ->exists();

        if ($ada) {
            throw new InvalidArgumentException('Tanggal izin ini tumpang-tindih dengan izin lain yang masih diajukan/disetujui.');
        }

        // Cek H-minimum sebelum izin mulai
        $hMinimum = Setting::getTenantValue('kesantrian.approval_h_minimum', 3);
        $hMulai = now()->diffInDays($tglMulai, false);

        if ($hMulai < $hMinimum) {
            throw new InvalidArgumentException("Izin harus diajukan minimal H-{$hMinimum} sebelum tanggal mulai (saat ini H-{$hMulai}).");
        }

        // Cek kuota bulan ini
        $mksMulanIni = Setting::getTenantValue('kesantrian.maks_izin_bulan', 2);
        $bulanIni = $tglMulai->format('Y-m');

        $sudahDiajukan = IzinSantri::where('tenant_id', tenant('id'))
            ->where('santri_id', $santri->id)
            ->whereIn('status', ['diajukan', 'disetujui'])
            ->whereRaw("TO_CHAR(tanggal_mulai, 'YYYY-MM') = ?", [$bulanIni])
            ->count();

        if ($sudahDiajukan >= $mksMulanIni) {
            throw new InvalidArgumentException("Kuota izin bulan ini ({$mksMulanIni}) sudah habis.");
        }
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
