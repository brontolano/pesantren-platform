<?php

namespace Tests\Feature;

use App\Models\ModuleRegistry;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleRegistrySeederTest extends TestCase
{
    use RefreshDatabase;

    /** Modul yang dikunci core (keputusan #1, 2026-06-09). */
    private const EXPECTED_CORE = ['core', 'kesantrian', 'keuangan', 'notifikasi', 'cms'];

    public function test_seeder_menghasilkan_katalog_lengkap(): void
    {
        $this->seed(ModuleRegistrySeeder::class);

        // 18 modul fungsional (5 core + 13 released) + file_storage & ai
        // (platform/development) = 20.
        $this->assertSame(20, ModuleRegistry::query()->count());
    }

    public function test_file_storage_tetap_development_tidak_diubah(): void
    {
        $this->seed(ModuleRegistrySeeder::class);

        $this->assertSame('development', ModuleRegistry::query()->where('key', 'file_storage')->value('status'));
    }

    public function test_hanya_lima_modul_yang_core_sesuai_keputusan(): void
    {
        $this->seed(ModuleRegistrySeeder::class);

        $coreKeys = ModuleRegistry::query()->where('is_core', true)->pluck('key')->sort()->values()->all();

        $this->assertSame(
            collect(self::EXPECTED_CORE)->sort()->values()->all(),
            $coreKeys,
            'Daftar modul core harus persis 5 sesuai keputusan #1'
        );
    }

    public function test_seeder_mengonvergensikan_klasifikasi_lama(): void
    {
        // Set kondisi awal EKSPLISIT (jangan andalkan default migrasi yang bisa
        // terpolusi test shared-DB lain dalam satu proses): keuangan is_core=false.
        ModuleRegistry::query()->updateOrCreate(
            ['key' => 'keuangan'],
            ['label' => 'Keuangan', 'status' => 'released', 'is_core' => false, 'sort' => 2],
        );
        $this->assertFalse((bool) ModuleRegistry::query()->where('key', 'keuangan')->value('is_core'));

        $this->seed(ModuleRegistrySeeder::class);

        // Seeder harus mengoreksi klasifikasi ke final (core) tanpa menggandakan baris.
        $this->assertTrue((bool) ModuleRegistry::query()->where('key', 'keuangan')->value('is_core'));
        $this->assertSame(1, ModuleRegistry::query()->where('key', 'keuangan')->count());
    }

    public function test_seeder_idempotent_dijalankan_dua_kali(): void
    {
        $this->seed(ModuleRegistrySeeder::class);
        $this->seed(ModuleRegistrySeeder::class);

        $this->assertSame(20, ModuleRegistry::query()->count());
        // Tiap key unik — tak ada duplikat.
        $this->assertSame(20, ModuleRegistry::query()->distinct()->count('key'));
    }
}
