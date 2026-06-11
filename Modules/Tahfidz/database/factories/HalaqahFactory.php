<?php

namespace Modules\Tahfidz\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tahfidz\Models\Halaqah;

class HalaqahFactory extends Factory
{
    protected $model = Halaqah::class;

    public function definition(): array
    {
        return [
            'nama' => 'Halaqah '.$this->faker->firstName(),
            'musyrif_id' => fn () => User::create([
                'name' => 'Musyrif '.$this->faker->name(),
                'email' => $this->faker->unique()->safeEmail(),
                'password' => 'password',
            ])->id,
            'jenis' => $this->faker->randomElement(['tahfidz', 'kitab', 'diniyah']),
            'aktif' => true,
        ];
    }
}
