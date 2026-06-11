<?php

namespace Modules\Akademik\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Akademik\Models\PresensiSantri;
use RuntimeException;

/**
 * F2-PWA — Jurnal KBM 30-detik menempel di layar presensi.
 *
 * Idempoten per (guru, jadwal, tanggal): simpan ulang meng-UPDATE baris
 * yang sama (unique parsial jurnal_mengajar_guru_jadwal_tanggal_unique, F0).
 * jumlah_hadir TIDAK dipercaya dari klien — dihitung ulang dari tabel
 * presensi_santri (kelas sesi + tanggal, status hadir).
 */
class JurnalKbmService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function simpan(User $guru, JadwalPelajaran $jadwal, array $data): JurnalMengajar
    {
        $this->ensureTenantContext();

        Validator::make($data, [
            'tanggal' => ['nullable', 'date', 'before_or_equal:'.now()->toDateString()],
            'materi' => ['nullable', 'string', 'max:2000'],
            'metode' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:terlaksana,kosong,diganti'],
            'pengganti_id' => ['nullable', 'integer', 'exists:users,id', 'required_if:status,diganti'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        if ($data['status'] === 'terlaksana' && blank($data['materi'] ?? null)) {
            throw ValidationException::withMessages([
                'materi' => 'Materi wajib diisi bila KBM terlaksana.',
            ]);
        }

        // Pengganti = penerima poin khidmah (WP-13) — wajib guru sungguhan
        // dan bukan diri sendiri (cegah kredit ke akun wali/santri/sendiri).
        if ($data['status'] === 'diganti') {
            $penggantiId = (int) ($data['pengganti_id'] ?? 0);

            if ($penggantiId === $guru->id) {
                throw ValidationException::withMessages([
                    'pengganti_id' => 'Pengganti tidak boleh diri sendiri.',
                ]);
            }

            $pengganti = User::query()->find($penggantiId);
            if ($pengganti === null || ! $pengganti->hasAnyRole(['guru', 'guru_tahfidz', 'asatidz', 'kepala'])) {
                throw ValidationException::withMessages([
                    'pengganti_id' => 'Pengganti harus akun pengajar (guru/asatidz).',
                ]);
            }
        }

        $tanggal = $data['tanggal'] ?? now()->toDateString();

        return JurnalMengajar::query()->updateOrCreate(
            [
                'guru_id' => $guru->id,
                'jadwal_pelajaran_id' => $jadwal->id,
                'tanggal' => $tanggal,
            ],
            [
                'halaqah_id' => null,
                'jam_mulai' => $jadwal->jam_mulai,
                'jam_selesai' => $jadwal->jam_selesai,
                'materi' => $data['materi'] ?? null,
                'metode' => $data['metode'] ?? null,
                'jumlah_hadir' => $this->hitungHadir($jadwal, $tanggal),
                'status' => $data['status'],
                'pengganti_id' => $data['status'] === 'diganti' ? ($data['pengganti_id'] ?? null) : null,
                'catatan' => $data['catatan'] ?? null,
            ],
        );
    }

    /** Hadir auto-count dari presensi yang TERSIMPAN — bukan angka klien. */
    public function hitungHadir(JadwalPelajaran $jadwal, string $tanggal): int
    {
        return PresensiSantri::query()
            ->where('kelas_id', $jadwal->kelas_id)
            ->whereDate('tanggal', $tanggal)
            ->where('status', 'hadir')
            ->count();
    }

    /**
     * Rekap bulanan per guru: jumlah sesi terlaksana/kosong/diganti.
     *
     * @return array<int, array{guru_id:int, guru:string, terlaksana:int, kosong:int, diganti:int, total:int}>
     */
    public function rekapBulanan(string $bulan): array
    {
        $this->ensureTenantContext();

        $awal = $bulan.'-01';
        $akhir = date('Y-m-t', strtotime($awal));

        return JurnalMengajar::query()
            ->with('guru:id,name')
            ->whereBetween('tanggal', [$awal, $akhir])
            ->get()
            ->groupBy('guru_id')
            ->map(function ($rows): array {
                $first = $rows->first();

                return [
                    'guru_id' => (int) $first->guru_id,
                    'guru' => $first->guru?->name ?? '—',
                    'terlaksana' => $rows->where('status', 'terlaksana')->count(),
                    'kosong' => $rows->where('status', 'kosong')->count(),
                    'diganti' => $rows->where('status', 'diganti')->count(),
                    'total' => $rows->count(),
                ];
            })
            ->sortBy('guru')
            ->values()
            ->all();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk jurnal KBM.');
        }
    }
}
