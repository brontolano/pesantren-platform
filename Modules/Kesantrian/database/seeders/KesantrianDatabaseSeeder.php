<?php

namespace Modules\Kesantrian\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

class KesantrianDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder ini harus dijalankan dalam konteks tenant
        // Contoh: tenancy()->initialize($tenant); lalu jalankan seeder

        if (! tenancy()->initialized) {
            $this->command->warn('Seeder Kesantrian harus dijalankan dalam konteks tenant. Lewati.');

            return;
        }

        $tenantId = tenant()->getTenantKey();

        // Buat kelas contoh
        $kelasData = [
            ['nama_kelas' => 'VII-A', 'tingkat' => '7', 'capacity' => 35],
            ['nama_kelas' => 'VII-B', 'tingkat' => '7', 'capacity' => 35],
            ['nama_kelas' => 'VIII-A', 'tingkat' => '8', 'capacity' => 35],
            ['nama_kelas' => 'VIII-B', 'tingkat' => '8', 'capacity' => 35],
            ['nama_kelas' => 'IX-A', 'tingkat' => '9', 'capacity' => 35],
            ['nama_kelas' => 'IX-B', 'tingkat' => '9', 'capacity' => 35],
        ];

        $kelasModels = [];
        foreach ($kelasData as $kd) {
            $kelasModels[] = Kelas::create(array_merge($kd, ['tenant_id' => $tenantId]));
        }

        // Buat santri dummy
        $namaLaki = ['Ahmad', 'Muhammad', 'Abdullah', 'Umar', 'Ali', 'Hasan', 'Husain', 'Ibrahim', 'Yusuf', 'Ismail'];
        $namaPerempuan = ['Aisyah', 'Fatimah', 'Khadijah', 'Maryam', 'Hafshah', 'Zainab', 'Ruqayyah', 'Safiyyah', 'Halimah', 'Aminah'];
        $namaKeluarga = ['Pratama', 'Saputra', 'Hidayat', 'Ramadhan', 'Nugraha', 'Wijaya', 'Santoso', 'Hakim', 'Firdaus', 'Maulana'];

        $counter = 1;
        foreach ($kelasModels as $kelas) {
            $jumlah = rand(5, 8);
            for ($i = 0; $i < $jumlah; $i++) {
                $isLaki = rand(0, 1);
                $namaDepan = $isLaki ? $namaLaki[array_rand($namaLaki)] : $namaPerempuan[array_rand($namaPerempuan)];
                $namaBelakang = $namaKeluarga[array_rand($namaKeluarga)];

                Santri::create([
                    'tenant_id' => $tenantId,
                    'nis' => sprintf('S%s%04d', date('Y'), $counter++),
                    'nama' => "$namaDepan $namaBelakang",
                    'tanggal_lahir' => now()->subYears(rand(12, 16))->subDays(rand(0, 365)),
                    'tempat_lahir' => ['Bandung', 'Jakarta', 'Surabaya', 'Sumedang', 'Garut'][array_rand(['Bandung', 'Jakarta', 'Surabaya', 'Sumedang', 'Garut'])],
                    'jenis_kelamin' => $isLaki ? 'L' : 'P',
                    'asal_sekolah' => 'SD/MI '.['Al-Ikhlas', 'Nurul Huda', 'Baitul Ilmi', 'An-Nuur', 'Al-Hikmah'][array_rand(['Al-Ikhlas', 'Nurul Huda', 'Baitul Ilmi', 'An-Nuur', 'Al-Hikmah'])],
                    'alamat' => 'Jl. Contoh No. '.rand(1, 100).', Kota '.['Bandung', 'Jakarta', 'Surabaya'][array_rand(['Bandung', 'Jakarta', 'Surabaya'])],
                    'no_hp_wali' => '08'.rand(1000000000, 9999999999),
                    'nama_ayah' => $namaLaki[array_rand($namaLaki)].' '.$namaBelakang,
                    'nama_ibu' => $namaPerempuan[array_rand($namaPerempuan)].' '.$namaKeluarga[array_rand($namaKeluarga)],
                    'status' => 'aktif',
                    'tahun_angkatan' => '2026/2027',
                    'kelas_id' => $kelas->id,
                ]);
            }
        }
    }
}
