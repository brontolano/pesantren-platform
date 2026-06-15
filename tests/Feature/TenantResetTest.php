<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantRecoveryService;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * RESET keseluruhan data satu tenant: backup-pengaman dipanggil DULU, data
 * operasional terhapus, fondasi (peran + COA) ter-reseed, admin baru dibuat.
 *
 * Backup shell (pg_dump/tar) di-stub karena pipe shell Symfony Process tak andal
 * di dev Windows; LOGIKA DESTRUKTIF (DROP SCHEMA + migrate ulang + provision) tetap
 * berjalan nyata pada DB FISIK milik tenant ini (aman — bukan DB test bersama).
 */
class TenantResetTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_reset_backup_dulu_lalu_kosongkan_dan_reseed_fondasi(): void
    {
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $tenant = $this->createProvisionedTenant('reset-'.str()->lower(str()->random(4)), 'Pesantren Reset', 'reset-uji.localhost');

        // Data operasional + baseline fondasi.
        tenancy()->initialize($tenant);
        Santri::create([
            'tenant_id' => $tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
        ]);
        $this->assertSame(1, DB::table('santri')->count());
        $this->assertGreaterThan(0, DB::table('roles')->count(), 'baseline: peran ada');
        $this->assertGreaterThan(0, DB::table('akun')->count(), 'baseline: COA ada');
        tenancy()->end();

        // RESET dgn backup-pengaman di-stub (tanpa shell).
        $svc = new class extends TenantRecoveryService
        {
            /** @var list<string> */
            public array $calls = [];

            public function backup(Tenant $tenant): string
            {
                $this->calls[] = 'db';

                return sys_get_temp_dir().DIRECTORY_SEPARATOR.'fake_'.$tenant->getKey().'.sql.gz';
            }

            public function backupFiles(Tenant $tenant): ?string
            {
                $this->calls[] = 'files';

                return null;
            }
        };

        $hasil = $svc->reset($tenant);

        // Backup pengaman terjadi (db + files dicoba) sebelum reseed.
        $this->assertContains('db', $svc->calls, 'backup DB pengaman wajib dipanggil');
        $this->assertContains('files', $svc->calls, 'backup file pengaman wajib dicoba');
        $this->assertNotEmpty($hasil['db_backup']);
        $this->assertNotNull($hasil['admin_password'], 'admin super baru dibuat (kredensial sekali tampil)');
        $this->assertNotNull($hasil['admin_email']);

        // Data operasional hilang; fondasi kembali.
        tenancy()->initialize($tenant);
        $this->assertSame(0, DB::table('santri')->count(), 'data santri terhapus oleh reset');
        $this->assertGreaterThan(0, DB::table('roles')->count(), 'peran ter-reseed');
        $this->assertGreaterThan(0, DB::table('akun')->count(), 'COA ter-reseed');
        tenancy()->end();
    }
}
