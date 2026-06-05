<?php

namespace Modules\PPDB\Database\Seeders;

use Illuminate\Database\Seeder;

class PPDBDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Berjalan di DATABASE TENANT (konteks tenancy aktif).
     */
    public function run(): void
    {
        $this->call([
            PesertaRoleSeeder::class, // F0.6 — role `peserta` portal SPMB
        ]);
    }
}
