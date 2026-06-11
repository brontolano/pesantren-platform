<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Pelamar;
use Modules\Payroll\Services\RekrutmenService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-19 — Rekrutmen Ringan.
 *
 * (a) Transisi tahap valid: maju satu tahap + catatan penilai per tahap;
 *     lompat tahap / keluar dari tahap final ditolak.
 * (b) Konversi "Jadikan Pegawai" membawa data (nama, is_alumni→asal_alumni)
 *     + idempoten (employee_id terisi → konversi ke-2 gagal, Employee tetap 1).
 * (c) Pelamar ditolak terarsip — tetap query-able via scope/filter tahap.
 */
class PelamarRekrutmenTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'employee', 'pelamar',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function buatPelamar(array $override = []): Pelamar
    {
        return Pelamar::create(array_merge([
            'nama' => 'Pelamar '.str()->random(5),
            'kontak' => '0812'.rand(10000000, 99999999),
            'asal' => 'Sumedang',
            'is_alumni' => true,
            'tahap' => 'lamaran',
        ], $override));
    }

    private function service(): RekrutmenService
    {
        return app(RekrutmenService::class);
    }

    // ---------------------------------------------------------------
    // (a) Transisi tahap
    // ---------------------------------------------------------------

    public function test_transisi_maju_satu_tahap_dengan_catatan_per_tahap(): void
    {
        $this->boot('rkt1');
        $p = $this->buatPelamar();

        $p = $this->service()->naikkanTahap($p, 'tes_baca_kitab', 'Bacaan fasih');
        $this->assertSame('tes_baca_kitab', $p->tahap);

        $p = $this->service()->naikkanTahap($p, 'microteaching', 'Penyampaian bagus');
        $p = $this->service()->naikkanTahap($p, 'wawancara', null);
        $p = $this->service()->naikkanTahap($p, 'diterima', 'Cocok untuk diniyah');

        $this->assertSame('diterima', $p->tahap);
        // Catatan tersimpan per tahap di JSON {tahap: catatan}.
        $this->assertSame([
            'tes_baca_kitab' => 'Bacaan fasih',
            'microteaching' => 'Penyampaian bagus',
            'diterima' => 'Cocok untuk diniyah',
        ], $p->catatan_penilai);
    }

    public function test_lompat_tahap_ditolak(): void
    {
        $this->boot('rkt2');
        $p = $this->buatPelamar(); // tahap lamaran

        $this->expectException(ValidationException::class);
        $this->service()->naikkanTahap($p, 'wawancara');
    }

    public function test_tahap_final_tidak_bisa_transisi_lagi(): void
    {
        $this->boot('rkt3');
        $p = $this->buatPelamar(['tahap' => 'ditolak']);

        $this->assertSame([], $p->tahapTujuanValid());

        $this->expectException(ValidationException::class);
        $this->service()->naikkanTahap($p, 'lamaran');
    }

    public function test_ditolak_sah_dari_tahap_mana_pun_non_final(): void
    {
        $this->boot('rkt4');
        $p = $this->buatPelamar();
        $p = $this->service()->naikkanTahap($p, 'tes_baca_kitab');
        $p = $this->service()->naikkanTahap($p, 'ditolak', 'Belum lulus tes kitab');

        $this->assertSame('ditolak', $p->tahap);
        $this->assertSame('Belum lulus tes kitab', $p->catatan_penilai['ditolak']);
    }

    // ---------------------------------------------------------------
    // (b) Konversi membawa data + idempoten
    // ---------------------------------------------------------------

    public function test_konversi_membawa_data_dan_idempoten(): void
    {
        $this->boot('rkt5');
        $p = $this->buatPelamar(['nama' => 'Ust Fulan', 'is_alumni' => true, 'tahap' => 'diterima']);

        $employee = $this->service()->jadikanPegawai($p);

        $this->assertSame('Ust Fulan', $employee->nama);
        $this->assertTrue((bool) $employee->asal_alumni, 'is_alumni pelamar → asal_alumni employee');
        $this->assertTrue((bool) $employee->aktif);
        $this->assertSame($employee->id, $p->refresh()->employee_id);

        // Konversi ke-2 WAJIB gagal — tidak boleh membuat Employee dobel.
        try {
            $this->service()->jadikanPegawai($p->refresh());
            $this->fail('Konversi kedua seharusnya melempar ValidationException');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, Employee::query()->where('nama', 'Ust Fulan')->count());
    }

    public function test_konversi_hanya_dari_tahap_diterima(): void
    {
        $this->boot('rkt6');
        $p = $this->buatPelamar(['tahap' => 'wawancara']);

        $this->expectException(ValidationException::class);
        $this->service()->jadikanPegawai($p);
    }

    // ---------------------------------------------------------------
    // (c) Ditolak terarsip
    // ---------------------------------------------------------------

    public function test_pelamar_ditolak_terarsip_dan_query_able(): void
    {
        $this->boot('rkt7');
        $aktif = $this->buatPelamar(['nama' => 'Calon Aktif']);
        $ditolakA = $this->buatPelamar(['nama' => 'Calon Ditolak A', 'tahap' => 'ditolak']);
        $ditolakB = $this->buatPelamar(['nama' => 'Calon Ditolak B', 'tahap' => 'ditolak']);

        // Baris ditolak TETAP ada (arsip, bukan delete).
        $this->assertSame(3, Pelamar::query()->count());

        $arsip = Pelamar::query()->ditolak()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$ditolakA->id, $ditolakB->id], $arsip);
        $this->assertNotContains($aktif->id, $arsip);
    }
}
