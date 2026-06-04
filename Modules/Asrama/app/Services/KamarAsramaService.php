<?php

namespace Modules\Asrama\Services;

use Illuminate\Validation\ValidationException;
use Modules\Asrama\Models\KamarAsrama;

class KamarAsramaService
{
    public function simpan(array $data, ?KamarAsrama $kamar = null): KamarAsrama
    {
        $blok = tenancy()->initialized
            ? \Modules\Asrama\Models\BlokAsrama::query()->findOrFail($data['blok_id'])
            : null;

        $totalExisting = $blok?->kamar()->when(
            $kamar !== null,
            fn ($query) => $query->whereKeyNot($kamar->getKey())
        )->count() ?? 0;

        if ($blok !== null && $totalExisting >= $blok->jumlah_kamar && $kamar === null) {
            throw ValidationException::withMessages([
                'blok_id' => 'Jumlah kamar di blok ini sudah mencapai batas yang ditetapkan.',
            ]);
        }

        $record = $kamar ?? new KamarAsrama();
        $record->fill($data);
        $record->save();

        return $record->fresh();
    }
}
