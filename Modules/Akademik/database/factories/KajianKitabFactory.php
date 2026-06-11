<?php

namespace Modules\Akademik\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\KajianKitab;
use Modules\Akademik\Models\Kitab;

class KajianKitabFactory extends Factory
{
    protected $model = KajianKitab::class;

    public function definition(): array
    {
        return [
            'kitab_id' => Kitab::factory(),
            'pengampu_id' => fn () => User::create([
                'name' => 'Pengampu '.$this->faker->name(),
                'email' => $this->faker->unique()->safeEmail(),
                'password' => 'password',
            ])->id,
            'metode' => $this->faker->randomElement(['sorogan', 'bandongan', 'wetonan']),
            'aktif' => true,
        ];
    }
}
