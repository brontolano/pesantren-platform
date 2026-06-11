<?php

namespace Modules\Akademik\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\Kitab;

class KitabFactory extends Factory
{
    protected $model = Kitab::class;

    public function definition(): array
    {
        return [
            'nama' => 'Kitab '.$this->faker->words(2, true),
            'pengarang' => $this->faker->name(),
            'kategori' => $this->faker->randomElement(['fiqih', 'nahwu', 'akhlak', 'hadits', 'tauhid']),
            'jumlah_halaman' => $this->faker->numberBetween(20, 600),
        ];
    }
}
