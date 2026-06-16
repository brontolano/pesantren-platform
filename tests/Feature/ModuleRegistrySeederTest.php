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

        // 20 modul fungsional (5 core + 15 released) + file_storage & ai
        // (platform/development) = 22.
        $this->assertSame(22, ModuleRegistry::query()->count());
    }

    public function test_crm_dan_koperasi_modul_opsional_released_non_core(): void
    {
        $this->seed(ModuleRegistrySeeder::class);

        foreach (['crm', 'koperasi'] as $key) {
            $m = ModuleRegistry::query()->where('key', $key)->first();
            $this->assertNotNull($m, "Modul {$key} wajib terdaftar (gerbang moduleVisible butuh registry).");
            $this->assertSame('released', $m->status);
            $this->assertFalse((bool) $m->is_core, "Modul {$key} harus opsional (non-core, bisa dimatikan).");
        }
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

        $this->assertSame(22, ModuleRegistry::query()->count());
        // Tiap key unik — tak ada duplikat.
        $this->assertSame(22, ModuleRegistry::query()->distinct()->count('key'));
    }
}
