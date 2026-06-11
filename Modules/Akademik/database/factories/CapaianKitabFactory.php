<?php

namespace Modules\Akademik\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\CapaianKitab;
use Modules\Akademik\Models\KajianKitab;

class CapaianKitabFactory extends Factory
{
    protected $model = CapaianKitab::class;

    public function definition(): array
    {
        return [
            'kajian_kitab_id' => KajianKitab::factory(),
            'santri_id' => null, // default: posisi kelompok (bandongan)
            'posisi' => 'Hal. '.$this->faker->numberBetween(1, 300),
            'tanggal' => $this->faker->date(),
            'status' => $this->faker->randomElement(['lanjut', 'ulang']),
        ];
    }
}
