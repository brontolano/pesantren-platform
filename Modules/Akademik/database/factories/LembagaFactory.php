<?php

namespace Modules\Akademik\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\Lembaga;

class LembagaFactory extends Factory
{
    protected $model = Lembaga::class;

    public function definition(): array
    {
        return [
            'nama' => 'Lembaga '.$this->faker->unique()->words(2, true),
            'jenis' => $this->faker->randomElement(Lembaga::JENIS),
            'jenjang' => null,
            'nomor_statistik' => null,
            'izin_operasional' => null,
            'tanggal_izin' => null,
            'kepala_id' => null,
            'divisi_unit_id' => null,
            'aktif' => true,
        ];
    }
}
