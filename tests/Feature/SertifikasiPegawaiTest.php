<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Modules\Payroll\Filament\Resources\Employee\Pages\CreateEmployee;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\SertifikasiPegawai;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-18 — Arsip Sertifikasi pegawai.
 *
 * (a) CRUD + tampil via relasi Employee::sertifikasi.
 * (b) CHECK constraint jenis: nilai di luar tpg|inpassing|gbpns|ppg ditolak DB.
 * (c) Form upload acceptedFileTypes WAJIB pdf/jpg/png (introspeksi pola
 *     KontrakKerjaTest — stored-XSS WP-08).
 * (d) /_tenant-storage/sertifikasi/* WAJIB login (direktori sensitif).
 */
class SertifikasiPegawaiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'employee', 'sertifikasi_pegawai', 'settings',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function buatEmployee(string $nama): Employee
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => $nama, 'email' => 'u'.str()->random(8).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);

        return Employee::create(['users_id' => $u->id, 'nama' => $nama, 'ptkp_status' => 'TK0', 'aktif' => true]);
    }

    // ---------------------------------------------------------------
    // (a) CRUD + relasi
    // ---------------------------------------------------------------

    public function test_crud_dan_tampil_via_relasi_employee(): void
    {
        $this->boot('srt1');
        $e = $this->buatEmployee('Ust Sertifikat');

        $s = SertifikasiPegawai::create([
            'employee_id' => $e->id, 'jenis' => 'tpg', 'nrg' => 'NRG-001',
            'nomor_sk' => 'SK/TPG/2026/01', 'nominal' => 1500000,
            'berlaku_mulai' => '2026-01-01', 'berlaku_selesai' => '2026-12-31',
            'file' => 'sertifikasi/sk-tpg.pdf',
        ]);

        // Read via relasi Employee.
        $this->assertSame(1, $e->sertifikasi()->count());
        $this->assertSame('SK/TPG/2026/01', $e->sertifikasi->first()->nomor_sk);
        $this->assertSame($e->id, $s->employee->id);

        // Queryable utk rekap kesiapan EMIS (WP-12 menyusul): filter per jenis.
        $this->assertSame(1, SertifikasiPegawai::query()->where('jenis', 'tpg')->count());
        $this->assertSame(0, SertifikasiPegawai::query()->where('jenis', 'ppg')->count());

        // Update + delete.
        $s->update(['nominal' => 1750000]);
        $this->assertSame('1750000.00', (string) $s->fresh()->nominal);
        $s->delete();
        $this->assertSame(0, SertifikasiPegawai::query()->count());
    }

    public function test_cascade_hapus_employee_ikut_menghapus_sertifikasi(): void
    {
        $this->boot('srt2');
        $e = $this->buatEmployee('Ust Cascade');
        SertifikasiPegawai::create(['employee_id' => $e->id, 'jenis' => 'ppg', 'nomor_sk' => 'SK/PPG/1']);

        $e->delete();
        $this->assertSame(0, SertifikasiPegawai::query()->count());
    }

    // ---------------------------------------------------------------
    // (b) CHECK jenis di DB
    // ---------------------------------------------------------------

    public function test_jenis_di_luar_daftar_ditolak_check_constraint(): void
    {
        $this->boot('srt3');
        $e = $this->buatEmployee('Ust Invalid');

        $this->expectException(QueryException::class);
        SertifikasiPegawai::create(['employee_id' => $e->id, 'jenis' => 'sembarang', 'nomor_sk' => 'SK/X']);
    }

    // ---------------------------------------------------------------
    // (c) acceptedFileTypes WAJIB pdf/jpg/png (WP-08)
    // ---------------------------------------------------------------

    public function test_form_upload_sertifikasi_hanya_menerima_pdf_jpg_png(): void
    {
        $this->boot('srt4');

        $livewire = app(CreateEmployee::class);
        $schema = EmployeeResource::form(Schema::make($livewire)->model(Employee::class));
        // getFlatComponents tidak turun ke child schema Repeater — flatten manual.
        $flatten = function (array $components) use (&$flatten): array {
            $hasil = [];
            foreach ($components as $c) {
                $hasil[] = $c;
                if (method_exists($c, 'getChildComponents')) {
                    $hasil = array_merge($hasil, $flatten($c->getChildComponents()));
                }
            }

            return $hasil;
        };

        $uploads = collect($flatten($schema->getFlatComponents(withHidden: true)))
            ->filter(fn ($c) => $c instanceof BaseFileUpload);

        $this->assertGreaterThan(0, $uploads->count(), 'Form Employee harus punya FileUpload arsip sertifikasi');
        foreach ($uploads as $upload) {
            $this->assertSame(
                ['application/pdf', 'image/jpeg', 'image/png'],
                $upload->getAcceptedFileTypes(),
                'acceptedFileTypes WAJIB pdf/jpg/png saja — exe/svg/html DITOLAK (stored-XSS WP-08)',
            );
        }
    }

    // ---------------------------------------------------------------
    // (d) Direktori sensitif: tanpa login → 403
    // ---------------------------------------------------------------

    public function test_tenant_storage_sertifikasi_wajib_login(): void
    {
        $tenant = $this->boot('srt5');
        tenancy()->end();

        $host = $tenant->domains()->first()->domain;
        $request = Request::create("http://{$host}/_tenant-storage/sertifikasi/sk-rahasia.pdf");
        $request->headers->set('Host', $host);

        $response = app(Kernel::class)->handle($request);
        $this->assertSame(403, $response->getStatusCode(), 'Arsip sertifikasi tanpa login WAJIB 403');
    }
}
