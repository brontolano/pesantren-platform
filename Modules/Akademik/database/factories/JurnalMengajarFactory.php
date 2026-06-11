<?php

namespace Modules\Akademik\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Tahfidz\Models\Halaqah;

class JurnalMengajarFactory extends Factory
{
    protected $model = JurnalMengajar::class;

    public function definition(): array
    {
        return [
            'guru_id' => fn () => User::create([
                'name' => 'Guru '.$this->faker->name(),
                'email' => $this->faker->unique()->safeEmail(),
                'password' => 'password',
            ])->id,
            // XOR: default konteks halaqah; override jadwal_pelajaran_id bila KBM formal.
            'halaqah_id' => Halaqah::factory(),
            'tanggal' => $this->faker->date(),
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'materi' => $this->faker->sentence(),
            'jumlah_hadir' => $this->faker->numberBetween(5, 40),
            'status' => 'terlaksana',
        ];
    }
}
