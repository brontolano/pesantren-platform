<?php

namespace Modules\Akademik\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\NgajiSesi;

class NgajiSesiFactory extends Factory
{
    protected $model = NgajiSesi::class;

    public function definition(): array
    {
        return [
            'kitab_id' => Kitab::factory(),
            'bab' => 'Bab '.$this->faker->word(),
            'tanggal' => $this->faker->date(),
            'metode' => $this->faker->randomElement(['bandongan', 'halaqah']),
        ];
    }
}
