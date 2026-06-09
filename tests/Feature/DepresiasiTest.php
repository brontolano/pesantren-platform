<?php

namespace Tests\Feature;

use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Services\AsetMovementService;
use Modules\Pembiayaan\Services\DepresiasiService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-13 — Penyusutan aset tetap (garis lurus + saldo menurun), posting jurnal
 * seimbang & idempoten, serta pelepasan (disposal) dengan aturan WAKAF tanpa P/L.
 */
class DepresiasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'jurnal', 'jurnal_baris', 'aset', 'jadwal_penyusutan', 'mutasi_aset',
            'approval_rule', 'approval_request', 'audit_log', 'settings',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    public function test_garis_lurus_menghasilkan_jadwal_rata(): void
    {
        $this->boot('dep1');
        $aset = Aset::create([
            'nama_aset' => 'Laptop', 'jenis' => 'peralatan',
            'nilai_perolehan' => 12000000, 'nilai' => 12000000, 'nilai_residu' => 0,
            'metode_penyusutan' => 'garis_lurus', 'umur_manfaat_bulan' => 12,
            'tanggal_perolehan' => '2026-01-15',
        ]);

        $jadwal = app(DepresiasiService::class)->buatJadwal($aset);
        $this->assertCount(12, $jadwal);
        $this->assertEqualsWithDelta(1000000, (float) $jadwal->first()->beban_penyusutan, 0.01);
        $this->assertEqualsWithDelta(12000000, (float) $jadwal->last()->akumulasi, 0.01);
        $this->assertEqualsWithDelta(0, (float) $jadwal->last()->nilai_akhir, 0.01);
        $this->assertEquals('2026-01', $jadwal->first()->periode);
        tenancy()->end();
    }

    public function test_saldo_menurun_berakhir_di_residu(): void
    {
        $this->boot('dep2');
        $aset = Aset::create([
            'nama_aset' => 'Kendaraan', 'jenis' => 'kendaraan',
            'nilai_perolehan' => 10000000, 'nilai' => 10000000, 'nilai_residu' => 1000000,
            'metode_penyusutan' => 'saldo_menurun', 'umur_manfaat_bulan' => 4,
        ]);

        $jadwal = app(DepresiasiService::class)->buatJadwal($aset);
        $this->assertCount(4, $jadwal);
        $this->assertEqualsWithDelta(5000000, (float) $jadwal->first()->beban_penyusutan, 0.01); // 10jt × 50%
        $this->assertEqualsWithDelta(1000000, (float) $jadwal->last()->nilai_akhir, 0.01);        // = residu
        $this->assertEqualsWithDelta(9000000, (float) $jadwal->sum(fn ($r) => (float) $r->beban_penyusutan), 0.01);
        tenancy()->end();
    }

    public function test_aset_wakaf_tidak_disusutkan(): void
    {
        $this->boot('dep3');
        $aset = Aset::create([
            'nama_aset' => 'Tanah Wakaf', 'jenis' => 'tanah', 'is_wakaf' => true,
            'nilai_perolehan' => 500000000, 'nilai' => 500000000,
            'metode_penyusutan' => 'garis_lurus', 'umur_manfaat_bulan' => 240,
        ]);

        $this->assertFalse($aset->bolehDisusutkan());
        $this->assertCount(0, app(DepresiasiService::class)->buatJadwal($aset));
        tenancy()->end();
    }

    public function test_posting_penyusutan_seimbang_dan_idempoten(): void
    {
        $this->boot('dep4');
        $aset = Aset::create([
            'nama_aset' => 'Komputer', 'jenis' => 'peralatan',
            'nilai_perolehan' => 12000000, 'nilai' => 12000000, 'nilai_residu' => 0,
            'metode_penyusutan' => 'garis_lurus', 'umur_manfaat_bulan' => 12,
            'tanggal_perolehan' => '2026-01-15',
        ]);
        $svc = app(DepresiasiService::class);
        $jadwal = $svc->buatJadwal($aset);
        $baris1 = $jadwal->first();

        $posted = $svc->postingPeriode($baris1);
        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->jurnal_id);

        $jurnal = Jurnal::with('baris')->find($posted->jurnal_id);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(1000000, (float) $jurnal->baris->sum('debit'), 0.01);

        $aset->refresh();
        $this->assertEqualsWithDelta(1000000, (float) $aset->akumulasi_penyusutan, 0.01);
        $this->assertEqualsWithDelta(11000000, (float) $aset->nilai, 0.01);

        // Idempoten: posting ulang baris yang sama tidak menggandakan jurnal.
        $jumlahJurnalSebelum = Jurnal::count();
        $svc->postingPeriode($baris1->fresh());
        $this->assertSame($jumlahJurnalSebelum, Jurnal::count());
        tenancy()->end();
    }

    public function test_disposal_non_wakaf_laba_seimbang(): void
    {
        $this->boot('dep5');
        $aset = Aset::create([
            'nama_aset' => 'Mesin', 'jenis' => 'peralatan',
            'nilai_perolehan' => 12000000, 'nilai' => 8000000,
            'akumulasi_penyusutan' => 4000000, 'metode_penyusutan' => 'garis_lurus',
            'umur_manfaat_bulan' => 12,
        ]);
        // Nilai buku 8jt, dijual 10jt → laba 2jt.
        $mutasi = app(AsetMovementService::class)->disposal($aset, ['harga_jual' => 10000000]);

        $this->assertSame('disposal', $mutasi->tipe);
        $this->assertSame('posted', $mutasi->status);
        $this->assertFalse($mutasi->tanpa_pl);
        $this->assertEqualsWithDelta(2000000, (float) $mutasi->laba_rugi, 0.01);
        $this->assertNotNull($mutasi->jurnal_id);

        $jurnal = Jurnal::with('baris')->find($mutasi->jurnal_id);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(14000000, (float) $jurnal->baris->sum('debit'), 0.01); // Kas10 + Akum4

        $this->assertSame('dilepas', $aset->fresh()->status_aset);
        tenancy()->end();
    }

    public function test_disposal_non_wakaf_rugi_seimbang(): void
    {
        $this->boot('dep6');
        $aset = Aset::create([
            'nama_aset' => 'Genset', 'jenis' => 'peralatan',
            'nilai_perolehan' => 12000000, 'nilai' => 8000000,
            'akumulasi_penyusutan' => 4000000, 'metode_penyusutan' => 'garis_lurus',
            'umur_manfaat_bulan' => 12,
        ]);
        // Nilai buku 8jt, dijual 5jt → rugi 3jt.
        $mutasi = app(AsetMovementService::class)->disposal($aset, ['harga_jual' => 5000000]);

        $this->assertEqualsWithDelta(-3000000, (float) $mutasi->laba_rugi, 0.01);
        $jurnal = Jurnal::with('baris')->find($mutasi->jurnal_id);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(12000000, (float) $jurnal->baris->sum('kredit'), 0.01); // K Aset 12jt
        tenancy()->end();
    }

    public function test_disposal_wakaf_tanpa_pl_tanpa_jurnal(): void
    {
        $this->boot('dep7');
        $aset = Aset::create([
            'nama_aset' => 'Gedung Wakaf', 'jenis' => 'bangunan', 'is_wakaf' => true,
            'nilai_perolehan' => 800000000, 'nilai' => 800000000,
            'metode_penyusutan' => 'none',
        ]);
        $mutasi = app(AsetMovementService::class)->disposal($aset, ['harga_jual' => 500000000]);

        $this->assertTrue($mutasi->tanpa_pl);
        $this->assertEqualsWithDelta(0, (float) $mutasi->laba_rugi, 0.01);
        $this->assertNull($mutasi->jurnal_id);          // TIDAK ada jurnal P/L (UU 41/2004)
        $this->assertSame('posted', $mutasi->status);
        $this->assertSame('dilepas', $aset->fresh()->status_aset);
        $this->assertSame(0, Jurnal::count());          // tak ada posting sama sekali
        tenancy()->end();
    }

    public function test_aset_wakaf_dipaksa_metode_none(): void
    {
        $this->boot('dep9');
        // Walau dikirim metode garis_lurus, hook saving memaksa 'none' (UU 41/2004).
        $aset = Aset::create([
            'nama_aset' => 'Sawah Wakaf', 'jenis' => 'tanah', 'is_wakaf' => true,
            'nilai_perolehan' => 200000000, 'nilai' => 200000000,
            'metode_penyusutan' => 'garis_lurus', 'umur_manfaat_bulan' => 120,
        ]);
        $this->assertSame('none', $aset->fresh()->metode_penyusutan);
        $this->assertFalse($aset->bolehDisusutkan());
        tenancy()->end();
    }

    public function test_disposal_ganda_ditolak(): void
    {
        $this->boot('dep10');
        $aset = Aset::create([
            'nama_aset' => 'AC', 'jenis' => 'peralatan',
            'nilai_perolehan' => 6000000, 'nilai' => 6000000,
        ]);
        $svc = app(AsetMovementService::class);
        $svc->disposal($aset, ['harga_jual' => 4000000]);

        $this->expectException(\InvalidArgumentException::class);
        $svc->disposal($aset->fresh(), ['harga_jual' => 1000000]); // pelepasan kedua ditolak
        tenancy()->end();
    }

    public function test_posting_tak_berurutan_nilai_tetap_konsisten(): void
    {
        $this->boot('dep11');
        $aset = Aset::create([
            'nama_aset' => 'Printer', 'jenis' => 'peralatan',
            'nilai_perolehan' => 12000000, 'nilai' => 12000000, 'nilai_residu' => 0,
            'metode_penyusutan' => 'garis_lurus', 'umur_manfaat_bulan' => 12,
            'tanggal_perolehan' => '2026-01-15',
        ]);
        $svc = app(DepresiasiService::class);
        $jadwal = $svc->buatJadwal($aset)->values();

        // Posting baris ke-2 lebih dulu, lalu ke-1 (di luar urutan).
        $svc->postingPeriode($jadwal->get(1));
        $svc->postingPeriode($jadwal->get(0));

        $aset->refresh();
        // Invarian nilai = perolehan − akumulasi tetap utuh meski urutan terbalik.
        $this->assertEqualsWithDelta(2000000, (float) $aset->akumulasi_penyusutan, 0.01);
        $this->assertEqualsWithDelta(10000000, (float) $aset->nilai, 0.01);
        tenancy()->end();
    }

    public function test_perolehan_nol_dibackfill_nilai_buku_tak_pernah_negatif(): void
    {
        $this->boot('dep12');
        // nilai_perolehan TIDAK diisi (default 0) — jebakan adversarial: tanpa
        // basis stabil, posting berulang bisa membuat nilai buku negatif.
        $aset = Aset::create([
            'nama_aset' => 'Meja', 'jenis' => 'peralatan',
            'nilai' => 10000000, 'nilai_residu' => 0,
            'metode_penyusutan' => 'garis_lurus', 'umur_manfaat_bulan' => 2,
            'tanggal_perolehan' => '2026-01-15',
        ]);
        // Hook saving membackfill nilai_perolehan dari nilai awal → basis stabil.
        $this->assertEqualsWithDelta(10000000, (float) $aset->fresh()->nilai_perolehan, 0.01);

        $svc = app(DepresiasiService::class);
        $svc->buatJadwal($aset);
        $svc->postingHinggaPeriode($aset->fresh(), '2026-12'); // posting kedua periode

        $aset->refresh();
        $this->assertEqualsWithDelta(10000000, (float) $aset->akumulasi_penyusutan, 0.01);
        $this->assertEqualsWithDelta(0, (float) $aset->nilai, 0.01);
        $this->assertGreaterThanOrEqual(0, (float) $aset->nilai); // TIDAK negatif
        tenancy()->end();
    }

    public function test_mutasi_lokasi_memperbarui_lokasi_tanpa_jurnal(): void
    {
        $this->boot('dep8');
        $aset = Aset::create([
            'nama_aset' => 'Proyektor', 'jenis' => 'peralatan',
            'nilai_perolehan' => 5000000, 'nilai' => 5000000, 'lokasi' => 'Gudang',
        ]);
        $mutasi = app(AsetMovementService::class)->mutasiLokasi($aset, 'Kelas 1A');

        $this->assertSame('mutasi_lokasi', $mutasi->tipe);
        $this->assertSame('Gudang', $mutasi->dari_lokasi);
        $this->assertSame('Kelas 1A', $aset->fresh()->lokasi);
        $this->assertSame(0, Jurnal::count());
        tenancy()->end();
    }
}
