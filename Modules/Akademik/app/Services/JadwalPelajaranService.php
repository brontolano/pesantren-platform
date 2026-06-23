<?php

namespace Modules\Akademik\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;

class JadwalPelajaranService
{
    /**
     * @throws ValidationException
     */
    public function simpan(array $data, ?JadwalPelajaran $jadwal = null): JadwalPelajaran
    {
        Validator::make($data, [
            'kelas_id' => ['required', 'integer'],
            'pelajaran_id' => ['required', 'integer'],
            'hari' => ['required', 'string'],
            'jam_mulai' => ['required'],
            'jam_selesai' => ['required', 'after:jam_mulai'],
            'ruangan' => ['nullable', 'string', 'max:255'],
        ])->validate();

        if ($jadwal instanceof JadwalPelajaran) {
            $jadwal->fill($data);
            $jadwal->save();

            return $jadwal->fresh();
        }

        return JadwalPelajaran::query()->create($data);
    }

    /**
     * gap-98: deteksi bentrok jadwal sebagai PERINGATAN LUNAK — tidak pernah
     * memblokir simpan. Mendeteksi tumpang-tindih jam pada hari yang sama untuk
     * kelas, guru (via pelajaran.guru_pengampu_id), atau ruangan yang sama.
     * Mengembalikan pesan ramah, atau null bila aman / data belum lengkap.
     */
    public static function cekBentrok(
        mixed $hari,
        mixed $jamMulai,
        mixed $jamSelesai,
        mixed $kelasId = null,
        mixed $pelajaranId = null,
        mixed $ruangan = null,
        ?int $ignoreId = null,
    ): ?string {
        if (! $hari || ! $jamMulai || ! $jamSelesai) {
            return null;
        }

        // guru diambil dari mapel yang dipilih
        $guruId = null;
        if ($pelajaranId) {
            $guruId = Pelajaran::query()
                ->whereKey((int) $pelajaranId)
                ->value('guru_pengampu_id');
        }

        // dasar: hari sama + jam tumpang-tindih (mulai<selesai_lain && selesai>mulai_lain)
        $dasar = JadwalPelajaran::query()
            ->where('hari', $hari)
            ->where('jam_mulai', '<', $jamSelesai)
            ->where('jam_selesai', '>', $jamMulai);

        if ($ignoreId !== null) {
            $dasar->whereKeyNot($ignoreId);
        }

        $pesan = [];

        if ($kelasId) {
            $bentrokKelas = (clone $dasar)->where('kelas_id', (int) $kelasId)->exists();
            if ($bentrokKelas) {
                $pesan[] = 'kelas ini sudah punya jadwal lain di waktu tersebut';
            }
        }

        if ($guruId) {
            $bentrokGuru = (clone $dasar)
                ->whereHas('pelajaran', fn ($q) => $q->where('guru_pengampu_id', $guruId))
                ->exists();
            if ($bentrokGuru) {
                $pesan[] = 'guru pengampu sudah mengajar di waktu tersebut';
            }
        }

        if (is_string($ruangan) && trim($ruangan) !== '') {
            $bentrokRuangan = (clone $dasar)->where('ruangan', $ruangan)->exists();
            if ($bentrokRuangan) {
                $pesan[] = 'ruangan ini sudah dipakai di waktu tersebut';
            }
        }

        if ($pesan === []) {
            return null;
        }

        return 'Bentrok jadwal: '.implode('; ', $pesan).'.';
    }
}
