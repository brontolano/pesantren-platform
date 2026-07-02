<?php

namespace Modules\Akademik\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\SetoranSorogan;

class SetoranSoroganFactory extends Factory
{
    protected $model = SetoranSorogan::class;

    public function definition(): array
    {
        $dari = $this->faker->numberBetween(1, 200);

        return [
            'kitab_id' => Kitab::factory(),
            'bab' => 'Bab '.$this->faker->word(),
            'halaman_dari' => $dari,
            'halaman_sampai' => $dari + $this->faker->numberBetween(0, 5),
            'tanggal' => $this->faker->date(),
            'metode' => 'sorogan',
            'nilai_kelancaran' => $this->faker->numberBetween(60, 100),
            'nilai_makna' => $this->faker->numberBetween(60, 100),
            'nilai_nahwu' => $this->faker->numberBetween(60, 100),
            'status' => $this->faker->randomElement(['lanjut', 'ulang']),
        ];
    }
}
