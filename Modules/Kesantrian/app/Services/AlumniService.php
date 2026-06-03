<?php

namespace Modules\Kesantrian\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Kesantrian\Models\Alumni;

class AlumniService
{
    public function list(?string $tahunLulus = null): Collection
    {
        $query = Alumni::with('santri');

        if ($tahunLulus) {
            $query->where('tahun_lulus', $tahunLulus);
        }

        return $query->orderByDesc('tahun_lulus')->get();
    }

    public function update(Alumni $alumni, array $data): Alumni
    {
        $alumni->update($data);
        return $alumni->fresh();
    }
}
