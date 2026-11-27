<?php

namespace Database\Seeders;

use App\Models\ModuleCatalogItem;
use Illuminate\Database\Seeder;

class ModuleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['key' => 'identity', 'name' => 'Identitas organisasi', 'description' => 'Profil dan identitas dasar organisasi.', 'available' => true, 'implemented' => true],
            ['key' => 'public-site', 'name' => 'Situs publik', 'description' => 'Halaman informasi publik organisasi.', 'available' => false, 'implemented' => false],
            ['key' => 'ppdb', 'name' => 'PPDB', 'description' => 'Belum tersedia pada fondasi v2.', 'available' => false, 'implemented' => false],
            ['key' => 'kesantrian', 'name' => 'Kesantrian', 'description' => 'Belum tersedia pada fondasi v2.', 'available' => false, 'implemented' => false],
            ['key' => 'keuangan', 'name' => 'Keuangan', 'description' => 'Belum tersedia pada fondasi v2.', 'available' => false, 'implemented' => false],
            ['key' => 'portal-orang-tua', 'name' => 'Portal Orang Tua', 'description' => 'Belum tersedia pada fondasi v2.', 'available' => false, 'implemented' => false],
        ] as $module) {
            ModuleCatalogItem::query()->updateOrCreate(['key' => $module['key']], $module);
        }
    }
}
