<?php

namespace Modules\Akademik\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\KalenderAkademik;

class KalenderAkademikFactory extends Factory
{
    protected $model = KalenderAkademik::class;

    public function definition(): array
    {
        $mulai = $this->faker->dateTimeBetween('-1 month', '+2 months');

        return [
            'lembaga_id' => null,
            'tanggal_mulai' => $mulai->format('Y-m-d'),
            'tanggal_selesai' => $mulai->format('Y-m-d'),
            'jenis' => $this->faker->randomElement(KalenderAkademik::JENIS),
            'keterangan' => 'Agenda '.$this->faker->unique()->words(2, true),
        ];
    }
}
