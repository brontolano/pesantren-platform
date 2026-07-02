<?php

namespace Modules\Asrama\Services;

use Illuminate\Validation\ValidationException;
use Modules\Asrama\Models\BlokAsrama;

class BlokAsramaService
{
    public function simpan(array $data, ?BlokAsrama $blok = null): BlokAsrama
    {
        $jumlahKamarAktif = $blok?->kamar()->count() ?? 0;
        $jumlahKamar = (int) ($data['jumlah_kamar'] ?? 0);

        if ($jumlahKamar < $jumlahKamarAktif) {
            throw ValidationException::withMessages([
                'jumlah_kamar' => 'Jumlah kamar tidak boleh lebih kecil dari kamar yang sudah terdaftar.',
            ]);
        }

        $record = $blok ?? new BlokAsrama;
        $record->fill($data);
        $record->save();

        return $record->fresh();
    }
}
