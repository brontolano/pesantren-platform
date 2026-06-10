<?php

namespace Tests\Feature;

use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Models\Soal;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Bank Soal PPDB — paket + butir (PG berkunci / essay) + isolasi tenant. */
class BankSoalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'paket_soal', 'soal']);
        $this->truncatePpdbTables(['soal', 'paket_soal', 'domains', 'tenants']);
    }

    public function test_paket_dan_soal_pg_essay_tersimpan_berurut(): void
    {
        $tenant = $this->createRuntimeTenant('bso1', 'Pesantren Soal');
        tenancy()->initialize($tenant);

        $paket = PaketSoal::create(['nama' => 'Tulis Gel.1', 'kategori_ujian' => 'Tulis', 'durasi_menit' => 90]);
        $paket->soal()->createMany([
            ['tipe' => 'pg', 'pertanyaan' => '2+2?', 'pilihan' => ['A' => '3', 'B' => '4'], 'kunci' => 'B', 'bobot' => 2, 'sort' => 2],
            ['tipe' => 'essay', 'pertanyaan' => 'Jelaskan rukun iman.', 'bobot' => 5, 'sort' => 1],
        ]);

        $soal = $paket->fresh()->soal;
        $this->assertCount(2, $soal);
        $this->assertSame('essay', $soal[0]->tipe, 'Relasi berurut sort.');
        $this->assertSame(['A' => '3', 'B' => '4'], $soal[1]->pilihan);
        $this->assertSame('B', $soal[1]->kunci);
        $this->assertNull($soal[0]->kunci);
        tenancy()->end();
    }

    public function test_isolasi_tenant_bank_soal(): void
    {
        $a = $this->createRuntimeTenant('bso2', 'A');
        tenancy()->initialize($a);
        PaketSoal::create(['nama' => 'Milik A']);
        $this->assertSame(1, PaketSoal::count());
        tenancy()->end();

        $b = $this->createRuntimeTenant('bso3', 'B');
        tenancy()->initialize($b);
        $this->assertSame(0, PaketSoal::count(), 'Paket tenant lain tak terlihat.');
        tenancy()->end();
    }
}
