<?php

namespace Modules\Akademik\Services;

use Illuminate\Support\Facades\DB;
use Modules\Akademik\Models\PeriodeAkademik;
use RuntimeException;

class PeriodeAkademikService
{
    public function simpan(array $data, ?PeriodeAkademik $periode = null): PeriodeAkademik
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($data, $periode): PeriodeAkademik {
            if (($data['is_active'] ?? false) === true) {
                PeriodeAkademik::query()->update(['is_active' => false]);
            }

            if ($periode instanceof PeriodeAkademik) {
                $periode->fill($data);
                $periode->save();

                return $periode->fresh();
            }

            return PeriodeAkademik::query()->create($data);
        });
    }

    public function aktifkan(PeriodeAkademik $periode): PeriodeAkademik
    {
        return $this->simpan([
            'nama' => $periode->nama,
            'tahun_akademik' => $periode->tahun_akademik,
            'semester' => $periode->semester,
            'tanggal_mulai' => $periode->tanggal_mulai,
            'tanggal_akhir' => $periode->tanggal_akhir,
            'is_active' => true,
        ], $periode);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk modul akademik.');
        }
    }
}
