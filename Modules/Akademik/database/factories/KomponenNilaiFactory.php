<?php

namespace Modules\Akademik\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\KomponenNilai;

class KomponenNilaiFactory extends Factory
{
    protected $model = KomponenNilai::class;

    public function definition(): array
    {
        return [
            'pelajaran_id' => null, // default tenant
            'nama' => 'Komponen '.$this->faker->unique()->word(),
            'bobot' => $this->faker->numberBetween(10, 60),
            'urutan' => $this->faker->numberBetween(1, 5),
        ];
    }
}
