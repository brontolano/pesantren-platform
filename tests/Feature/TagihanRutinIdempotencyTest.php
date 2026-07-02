<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Models\TagihanRutin;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TagihanRutinService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Idempotensi JALUR UANG Tagihan Rutin (issue #335).
 *
 * Mengunci tiga invarian:
 *  1. Lapis ATURAN  — terbitkan() dua kali utk periode sama TIDAK menggandakan tagihan.
 *  2. Lapis SANTRI  — re-run --force hanya membuat tagihan utk santri yang BELUM punya
 *     (santri baru masuk), yang lama dilewati.
 *  3. Lapis LEDGER  — pelunasan tagihan rutin menghasilkan TEPAT SATU jurnal, verifikasi
 *     ulang ditolak, dan jurnal auto WAJIB punya ref + idempotency_key NON-NULL
 *     (pelajaran repo: idempotency_key NULL tidak ter-dedup oleh unique index SQL).
 */
class TagihanRutinIdempotencyTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('tagihan_rutin')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function buatSantri(string $nis): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
    }

    private function buatAturan(): TagihanRutin
    {
        return TagihanRutin::create([
            'nama' => 'SPP Bulanan',
            'jenis_tagihan' => 'spp',
            'frekuensi' => 'bulanan',
            'nominal' => 250000,
            'target_tipe' => 'semua',
            'tanggal_terbit' => 1,
            'tempo_hari' => 10,
            'aktif' => true,
        ]);
    }

    public function test_terbit_dua_kali_periode_sama_tidak_menggandakan_tagihan(): void
    {
        $this->bootTenant('rutin1');

        $this->buatSantri('RTN001');
        $this->buatSantri('RTN002');
        // Santri non-aktif TIDAK boleh kena tagihan.
        Santri::create([
            'nis' => 'RTN003', 'nama' => 'Santri Cuti', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'P', 'status' => 'cuti',
        ]);

        $aturan = $this->buatAturan();
        $service = new TagihanRutinService;

        $pertama = $service->terbitkan($aturan, '2026-07');
        $this->assertSame(2, $pertama['dibuat'], 'Terbit pertama harus membuat tagihan utk 2 santri aktif.');
        $this->assertSame(2, Tagihan::count());
        $this->assertSame('2026-07', $aturan->fresh()->terakhir_terbit_periode);

        // Lapis 1 (aturan): pemanggilan ulang periode sama = no-op total.
        $kedua = $service->terbitkan($aturan->fresh(), '2026-07');
        $this->assertSame(0, $kedua['dibuat'], 'Terbit ulang periode sama tidak boleh membuat tagihan baru.');
        $this->assertSame(0, $kedua['target'], 'Lapis aturan harus memotong sebelum resolve santri.');
        $this->assertSame(2, Tagihan::count(), 'Jumlah tagihan tidak boleh berubah (anti dobel).');

        // Tidak ada santri dgn >1 tagihan utk jenis+periode yang sama.
        $maxPerSantri = Tagihan::query()
            ->selectRaw('count(*) as c')
            ->groupBy('santri_id', 'jenis_tagihan', 'bulan_masehi')
            ->orderByDesc('c')
            ->value('c');
        $this->assertSame(1, (int) $maxPerSantri, 'Tiap santri maks 1 tagihan per jenis+periode.');
    }

    public function test_force_rerun_hanya_membuat_untuk_santri_baru(): void
    {
        $this->bootTenant('rutin2');

        $this->buatSantri('RTN010');
        $this->buatSantri('RTN011');

        $aturan = $this->buatAturan();
        $service = new TagihanRutinService;

        $service->terbitkan($aturan, '2026-07');
        $this->assertSame(2, Tagihan::count());

        // Santri baru masuk di tengah bulan → re-run --force.
        $this->buatSantri('RTN012');
        $force = $service->terbitkan($aturan->fresh(), '2026-07', force: true);

        $this->assertSame(3, $force['target']);
        $this->assertSame(1, $force['dibuat'], 'Force re-run hanya membuat utk santri baru.');
        $this->assertSame(2, $force['dilewati'], 'Santri yang sudah punya tagihan DILEWATI (lapis santri).');
        $this->assertSame(3, Tagihan::count());

        // Periode BERBEDA memang harus menerbitkan lagi (bukan dedup salah sasaran).
        $agustus = $service->terbitkan($aturan->fresh(), '2026-08');
        $this->assertSame(3, $agustus['dibuat'], 'Periode baru wajib terbit utk semua santri aktif.');
        $this->assertSame(6, Tagihan::count());
    }

    public function test_pelunasan_tagihan_rutin_satu_jurnal_ref_tidak_null_dan_verifikasi_ulang_ditolak(): void
    {
        $this->bootTenant('rutin3');
        (new LedgerSeeder)->run();

        $bendahara = User::withoutGlobalScopes()->create([
            'name' => 'Bendahara', 'email' => 'bendahara@rutin3.test',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);

        $this->buatSantri('RTN020');
        $aturan = $this->buatAturan();
        (new TagihanRutinService)->terbitkan($aturan, '2026-07');

        $tagihan = Tagihan::firstOrFail();
        $service = app(PembayaranService::class);

        $pembayaran = $service->inputPembayaran($tagihan, [
            'nominal' => 250000, 'metode' => 'tunai', 'created_by' => $bendahara->id,
        ]);
        $service->verifikasiPembayaran($pembayaran, $bendahara->id);

        $this->assertSame('lunas', $tagihan->fresh()->status);

        // TEPAT SATU jurnal, dengan ref & idempotency_key NON-NULL (NULL tak ter-dedup di SQL).
        $jurnals = Jurnal::where('ref_type', 'Pembayaran')->get();
        $this->assertCount(1, $jurnals, 'Pelunasan harus menghasilkan tepat satu jurnal.');
        $jurnal = $jurnals->first();
        $this->assertNotNull($jurnal->ref_id, 'ref_id jurnal auto tidak boleh NULL.');
        $this->assertSame((string) $pembayaran->id, (string) $jurnal->ref_id);
        $this->assertNotNull($jurnal->idempotency_key, 'idempotency_key tidak boleh NULL (unique index tak men-dedup NULL).');
        $this->assertEqualsWithDelta(250000, (float) $jurnal->baris()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta((float) $jurnal->baris()->sum('debit'), (float) $jurnal->baris()->sum('kredit'), 0.01);

        // Verifikasi ULANG pembayaran yang sama harus DITOLAK — tanpa jurnal tambahan.
        try {
            $service->verifikasiPembayaran($pembayaran->fresh(), $bendahara->id);
            $this->fail('Verifikasi ulang pembayaran terverifikasi harus melempar exception.');
        } catch (InvalidArgumentException) {
            // expected
        }
        $this->assertSame(1, Jurnal::where('ref_type', 'Pembayaran')->count(), 'Verifikasi ulang tidak boleh menambah jurnal.');
        $this->assertSame(1, Pembayaran::where('is_verified', true)->count());

        // Input pembayaran BARU pada tagihan lunas juga ditolak (anti dobel bayar).
        $this->expectException(InvalidArgumentException::class);
        $service->inputPembayaran($tagihan->fresh(), ['nominal' => 250000]);
    }
}
