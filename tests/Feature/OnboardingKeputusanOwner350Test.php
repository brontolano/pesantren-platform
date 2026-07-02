<?php

namespace Tests\Feature;

use App\Filament\Pages\SetupPesantren;
use App\Filament\Widgets\OnboardingChecklist;
use App\Models\Setting;
use App\Models\Tenant;
use App\Support\UnitSetupRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Keputusan owner #350 (poin 1-3):
 *  1. Flag WAJIB per unit configurable via Setting 'setup.unit_wajib'
 *     (default = semua wajib, perilaku lama).
 *  2. Skenario setup ('baru'|'migrasi') via Setting 'setup.skenario' —
 *     migrasi menonjolkan importer historis di Pusat Setup.
 *  3. Penunjuk "Lanjut ke" — unit/langkah pertama yang belum selesai.
 */
class OnboardingKeputusanOwner350Test extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'settings', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions',
            'santri', 'kelas', 'lembaga', 'periode_akademik',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('owner350', 'Pesantren Owner350');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        UnitSetupRegistry::flushCache();
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ---- Poin 1: unit wajib configurable ---------------------------------

    public function test_default_semua_unit_wajib(): void
    {
        foreach (array_keys(UnitSetupRegistry::units()) as $kode) {
            $this->assertTrue(UnitSetupRegistry::unitWajib($kode), "default: unit {$kode} wajib");
            $this->assertTrue(UnitSetupRegistry::progress($kode)['wajib']);
        }
    }

    public function test_setting_unit_wajib_mengubah_status_wajib(): void
    {
        Setting::updateOrCreate(
            ['key' => UnitSetupRegistry::SETTING_UNIT_WAJIB],
            ['value' => ['kesantrian', 'akademik']],
        );

        $this->assertTrue(UnitSetupRegistry::unitWajib('kesantrian'));
        $this->assertFalse(UnitSetupRegistry::unitWajib('keuangan'));
        $this->assertFalse(UnitSetupRegistry::progress('tahfidz')['wajib']);
    }

    public function test_set_unit_wajib_idempoten_dan_tak_menyentuh_unit_lain(): void
    {
        // Setting absen → toggle 1 unit memmaterialisasi daftar penuh dulu.
        UnitSetupRegistry::setUnitWajib('tahfidz', false);

        $this->assertFalse(UnitSetupRegistry::unitWajib('tahfidz'));
        foreach (array_keys(UnitSetupRegistry::units()) as $kode) {
            if ($kode !== 'tahfidz') {
                $this->assertTrue(UnitSetupRegistry::unitWajib($kode), "unit {$kode} tetap wajib");
            }
        }

        // Kembalikan → wajib lagi (idempoten, tanpa duplikat).
        UnitSetupRegistry::setUnitWajib('tahfidz', true);
        UnitSetupRegistry::setUnitWajib('tahfidz', true);
        $this->assertTrue(UnitSetupRegistry::unitWajib('tahfidz'));
        $v = Setting::getTenantValue(UnitSetupRegistry::SETTING_UNIT_WAJIB);
        $this->assertSame(count($v), count(array_unique($v)), 'daftar unit wajib tanpa duplikat');
    }

    // ---- Poin 2: skenario baru vs migrasi --------------------------------

    public function test_skenario_default_baru_dan_persist_menyimpan_pilihan(): void
    {
        $this->assertFalse(UnitSetupRegistry::skenarioMigrasi(), 'default = pesantren baru bersih');

        (new SetupPesantren)->persist(['skenario' => 'migrasi']);
        $this->assertTrue(UnitSetupRegistry::skenarioMigrasi());
        $this->assertSame('migrasi', Setting::getTenantStringValue(UnitSetupRegistry::SETTING_SKENARIO));

        // Bisa diubah balik belakangan.
        (new SetupPesantren)->persist(['skenario' => 'baru']);
        $this->assertFalse(UnitSetupRegistry::skenarioMigrasi());

        // Nilai tak dikenal diabaikan (tak menimpa).
        (new SetupPesantren)->persist(['skenario' => 'aneh']);
        $this->assertSame('baru', Setting::getTenantStringValue(UnitSetupRegistry::SETTING_SKENARIO));
    }

    // ---- Poin 3: penunjuk "Lanjut ke" ------------------------------------

    public function test_next_unit_menunjuk_unit_belum_tuntas_pertama_urutan_registry(): void
    {
        UnitSetupRegistry::flushCache();
        $next = UnitSetupRegistry::nextUnit();

        // Tenant kosong: unit pertama registry (kesantrian) belum tuntas.
        $this->assertNotNull($next);
        $this->assertSame('kesantrian', $next['kode']);

        // Unit pertama ditandai TIDAK wajib → penunjuk lompat ke unit wajib berikutnya.
        UnitSetupRegistry::setUnitWajib('kesantrian', false);
        $next = UnitSetupRegistry::nextUnit();
        $this->assertNotNull($next);
        $this->assertNotSame('kesantrian', $next['kode']);
    }

    public function test_checklist_resume_dan_langkah_resume_konsisten(): void
    {
        // langkahResume: belum ada apa pun → mulai dari langkah 1.
        $page = new SetupPesantren;
        $this->assertSame(1, $page->langkahResume());

        // Skenario terisi → resume melewati langkah "Mulai".
        (new SetupPesantren)->persist(['skenario' => 'baru']);
        $this->assertGreaterThan(1, $page->langkahResume());

        // Checklist: penunjuk = langkah PERTAMA yang belum done (derivasi buildSteps,
        // definisi yang sama dengan yang dirender widget — anti-drift).
        $steps = OnboardingChecklist::buildSteps();
        $lanjut = collect($steps)->first(fn (array $s): bool => ! $s['done']);
        $this->assertNotNull($lanjut, 'tenant baru pasti punya langkah belum selesai');
        foreach ($steps as $s) {
            if ($s['label'] === $lanjut['label']) {
                break;
            }
            $this->assertTrue($s['done'], 'semua langkah sebelum penunjuk harus sudah selesai');
        }
    }
}
