<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Modules\Akademik\Models\ProgramStudi;
use Modules\Akademik\Services\TranskripService;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Asrama\Services\PenempatanAsramaService;
use Modules\Asrama\Services\PenempatanOtomatisService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G3-22 — Penempatan kamar otomatis (gender/kapasitas/idempoten/grouping),
 * Program+outcome, transkrip via F5. Non-uang.
 */
class AsramaOtomatisTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri',
            'blok_asrama', 'kamar_asrama', 'penempatan_asrama',
            'program_studi', 'program_outcome', 'document', 'audit_log',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function svc(): PenempatanOtomatisService
    {
        return app(PenempatanOtomatisService::class);
    }

    private function santri(string $jk, int $angkatan = 2026): Santri
    {
        return Santri::create([
            'nis' => 'AS-'.str()->random(6), 'nama' => 'Santri '.$jk,
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => $jk, 'status' => 'aktif',
            'tahun_angkatan' => $angkatan,
        ]);
    }

    private function kamar(string $gender, int $kapasitas, string $kode = 'K'): KamarAsrama
    {
        $blok = BlokAsrama::create(['nama_blok' => 'Blok '.$kode.$gender, 'gender' => $gender, 'jumlah_kamar' => 1, 'is_active' => true]);

        return KamarAsrama::create(['blok_id' => $blok->id, 'nomor_kamar' => $kode, 'kapasitas' => $kapasitas, 'is_active' => true]);
    }

    public function test_gender_constraint_tak_pernah_lintas(): void
    {
        $this->boot('as1');
        $kPutra = $this->kamar('putra', 5, 'A');
        $kPutri = $this->kamar('putri', 5, 'B');
        $l = $this->santri('L');
        $p = $this->santri('P');

        $this->svc()->tempatkanOtomatis();

        $this->assertSame($kPutra->id, PenempatanAsrama::where('santri_id', $l->id)->where('is_current', true)->value('kamar_id'));
        $this->assertSame($kPutri->id, PenempatanAsrama::where('santri_id', $p->id)->where('is_current', true)->value('kamar_id'));
        tenancy()->end();
    }

    public function test_kapasitas_anti_overflow(): void
    {
        $this->boot('as2');
        $this->kamar('putra', 2, 'A'); // hanya 2 slot
        $this->santri('L');
        $this->santri('L');
        $this->santri('L'); // 3 santri, 2 slot

        $hasil = $this->svc()->tempatkanOtomatis();
        $this->assertSame(2, $hasil['ditempatkan']);
        $this->assertSame(1, $hasil['gagal']);
        // Tak ada kamar yang overflow.
        $this->assertSame(2, PenempatanAsrama::where('is_current', true)->count());
        tenancy()->end();
    }

    public function test_idempoten_tak_double_place(): void
    {
        $this->boot('as3');
        $this->kamar('putra', 5, 'A');
        $this->santri('L');
        $this->santri('L');

        $h1 = $this->svc()->tempatkanOtomatis();
        $this->assertSame(2, $h1['ditempatkan']);
        // Re-run → tak menempatkan lagi (sudah punya kamar aktif).
        $h2 = $this->svc()->tempatkanOtomatis();
        $this->assertSame(0, $h2['ditempatkan']);
        $this->assertSame(2, PenempatanAsrama::where('is_current', true)->count());
        tenancy()->end();
    }

    public function test_grouping_seangkatan_diutamakan(): void
    {
        $this->boot('as4');
        $blok = BlokAsrama::create(['nama_blok' => 'Putra', 'gender' => 'putra', 'jumlah_kamar' => 2, 'is_active' => true]);
        $k1 = KamarAsrama::create(['blok_id' => $blok->id, 'nomor_kamar' => 'K1', 'kapasitas' => 3, 'is_active' => true]);
        $k2 = KamarAsrama::create(['blok_id' => $blok->id, 'nomor_kamar' => 'K2', 'kapasitas' => 3, 'is_active' => true]);

        // A (2025) ditempatkan manual ke K1.
        $a = $this->santri('L', 2025);
        app(PenempatanAsramaService::class)->tempatkan($a, $k1);

        // B (2025) via otomatis → harus ke K1 (seangkatan), bukan K2.
        $b = $this->santri('L', 2025);
        $this->svc()->tempatkanOtomatis();
        $this->assertSame($k1->id, PenempatanAsrama::where('santri_id', $b->id)->where('is_current', true)->value('kamar_id'));
        tenancy()->end();
    }

    public function test_unique_index_cegah_dua_penempatan_aktif(): void
    {
        $this->boot('as7');
        $k = $this->kamar('putra', 5, 'A');
        $s = $this->santri('L');
        PenempatanAsrama::create(['santri_id' => $s->id, 'kamar_id' => $k->id, 'tanggal_masuk' => now()->toDateString(), 'status' => 'aktif', 'is_current' => true]);

        // Jaring DB: penempatan is_current kedua untuk santri sama DITOLAK.
        $this->expectException(QueryException::class);
        PenempatanAsrama::create(['santri_id' => $s->id, 'kamar_id' => $k->id, 'tanggal_masuk' => now()->toDateString(), 'status' => 'aktif', 'is_current' => true]);
        tenancy()->end();
    }

    public function test_program_dan_outcome(): void
    {
        $this->boot('as5');
        $p = ProgramStudi::create(['nama' => 'Tahfidz', 'jenjang' => 'Mahad', 'is_active' => true]);
        $p->outcome()->create(['kode' => 'O1', 'deskripsi' => 'Hafal 30 juz', 'urutan' => 1]);
        $p->outcome()->create(['kode' => 'O2', 'deskripsi' => 'Tajwid mutqin', 'urutan' => 2]);

        $this->assertCount(2, $p->fresh()->outcome);
        $this->assertSame('Hafal 30 juz', $p->fresh()->outcome->first()->deskripsi);
        tenancy()->end();
    }

    public function test_transkrip_terbit_sebagai_dokumen_f5(): void
    {
        $this->boot('as6');
        $s = $this->santri('L');
        $doc = app(TranskripService::class)->terbitkan($s, ['jenis' => 'ijazah', 'periode' => '2026']);

        $this->assertSame('ijazah', $doc->kategori);
        $this->assertSame(1, $s->documents()->where('kategori', 'ijazah')->count());
        $this->assertNotNull($s->dokumenTerbaru('ijazah'));
        tenancy()->end();
    }

    public function test_isolasi_tenant_program(): void
    {
        $this->boot('asA');
        ProgramStudi::create(['nama' => 'X', 'is_active' => true]);
        $this->assertSame(1, ProgramStudi::query()->count());
        tenancy()->end();

        $this->boot('asB');
        $this->assertSame(0, ProgramStudi::query()->count(), 'Program tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}
