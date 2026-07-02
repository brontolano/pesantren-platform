<?php

namespace Tests\Feature;

use App\Filament\Pages\SetupPesantren;
use App\Filament\Widgets\OnboardingChecklist;
use App\Models\Setting;
use App\Models\Tenant;
use App\Support\UnitSetupRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Anti-drift #349: TIGA indikator progres onboarding (OnboardingChecklist,
 * SetupPesantren::progress(), UnitSetupRegistry) harus punya SATU definisi
 * "selesai". OnboardingChecklist kini murni derivasi — test ini mengunci agar
 * definisi tak diduplikasi lagi diam-diam (mis. wizard bilang selesai tapi
 * checklist bilang belum).
 */
class OnboardingProgressKonsistensiTest extends TestCase
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

        $this->tenant = $this->createRuntimeTenant('drift', 'Pesantren Drift');
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

    /** Cari langkah checklist berdasar label. */
    private function stepChecklist(string $label): array
    {
        $step = collect(OnboardingChecklist::buildSteps())->firstWhere('label', $label);
        $this->assertNotNull($step, "langkah checklist '{$label}' harus ada");

        return $step;
    }

    public function test_langkah_fondasi_checklist_identik_dengan_setup_pesantren_progress(): void
    {
        // Sebelum: keduanya belum selesai.
        $this->assertFalse(SetupPesantren::progress()['selesai']);
        $this->assertFalse($this->stepChecklist('Selesaikan Setup Pesantren')['done']);

        // Tandai fondasi selesai (kunci yang sama yang ditulis wizard).
        Setting::updateOrCreate(['key' => 'setup.selesai'], ['value' => ['at' => now()->toIso8601String()]]);

        $this->assertTrue(SetupPesantren::progress()['selesai']);
        $this->assertSame(
            SetupPesantren::progress()['selesai'],
            $this->stepChecklist('Selesaikan Setup Pesantren')['done'],
            'checklist HARUS derivasi dari SetupPesantren::progress(), bukan cek sendiri',
        );
    }

    public function test_langkah_data_checklist_identik_dengan_registry_step_done(): void
    {
        UnitSetupRegistry::flushCache();
        $this->assertFalse(UnitSetupRegistry::stepDone('kesantrian', 'santri'));
        $this->assertFalse($this->stepChecklist('Masukkan data santri')['done']);

        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
        ]);
        UnitSetupRegistry::flushCache();

        $this->assertTrue(UnitSetupRegistry::stepDone('kesantrian', 'santri'));
        $this->assertSame(
            UnitSetupRegistry::stepDone('kesantrian', 'santri'),
            $this->stepChecklist('Masukkan data santri')['done'],
        );

        // Wali & website: nilai apa pun, HARUS sama dengan registry (satu sumber).
        $this->assertSame(
            UnitSetupRegistry::stepDone('kesantrian', 'wali'),
            $this->stepChecklist('Tautkan akun wali')['done'],
        );
        $this->assertSame(
            UnitSetupRegistry::stepDone('kelembagaan', 'website'),
            $this->stepChecklist('Tampilkan website pondok')['done'],
        );
    }

    public function test_langkah_opsional_tidak_menahan_tuntas_dan_verifikasi_konsisten(): void
    {
        // Semua langkah opsional (importer historis) TIDAK masuk hitungan wajib.
        foreach (UnitSetupRegistry::units() as $kode => $def) {
            $wajib = collect($def['steps'])->where('opsional', false)->count();
            $prog = UnitSetupRegistry::progress($kode);
            $this->assertSame($wajib, $prog['total'], "total unit {$kode} = langkah wajib saja");
            $this->assertSame(
                collect($def['steps'])->where('opsional', true)->count(),
                $prog['opsional_total'],
            );
        }

        // Importer historis yang dijanjikan #346 benar-benar terekspos sebagai langkah opsional.
        $opsional = [];
        foreach (UnitSetupRegistry::units() as $kode => $def) {
            foreach ($def['steps'] as $s) {
                if ($s['opsional']) {
                    $opsional[] = $kode.'.'.$s['key'];
                }
            }
        }
        foreach ([
            'akuntansi.saldo_awal', 'keuangan.saldo_tabungan', 'akademik.nilai',
            'akademik.presensi_santri', 'kesantrian.presensi_asrama', 'kesantrian.pelanggaran',
            'kepegawaian.penugasan', 'kepegawaian.presensi_staf', 'tahfidz.hafalan',
        ] as $k) {
            $this->assertContains($k, $opsional, "langkah opsional {$k} harus terdaftar");
        }

        // Gate verifikasi (#347): status kuning saat langkah wajib belum lengkap,
        // dan tak pernah melempar (toleran, aman untuk panel).
        $v = UnitSetupRegistry::verifikasi('kesantrian');
        $this->assertContains($v['status'], ['hijau', 'kuning', 'merah']);
        $this->assertNotEmpty($v['items']);
        $this->assertSame(
            UnitSetupRegistry::progress('kesantrian')['tuntas'],
            $v['items'][0]['ok'],
            'item pertama verifikasi = kelengkapan langkah wajib registry',
        );
    }
}
