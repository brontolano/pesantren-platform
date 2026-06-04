<?php

namespace Modules\Akademik\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\JadwalPelajaran;

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
}
