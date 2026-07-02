<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Events\PembayaranDiverifikasi;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Listeners\PostingPembayaranListener;
use Modules\Ledger\Models\Jurnal;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class LedgerAutoJurnalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('jurnal_baris')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'domains',
            'tenants',
        ]);
    }

    /** @return array{0: User, 1: Pembayaran} */
    private function buatPembayaran(float $nominal = 150000): array
    {
        // Sisipkan baris tenant ke DB tenant — target FK users/santri.
        $this->seedTenantRow(tenancy()->tenant);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Bendahara',
            'email' => 'bendahara@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        $santri = Santri::create([
            'nis' => 'S-'.str()->random(5),
            'nama' => 'Santri Uji',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
        ]);

        $tagihan = Tagihan::create([
            'santri_id' => $santri->id,
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'nominal_akhir' => $nominal,
            'jatuh_tempo' => '2026-06-30',
            'status' => 'belum_bayar',
        ]);

        $pembayaran = Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'santri_id' => $santri->id,
            'nominal' => $nominal,
            'metode' => 'tunai',
            'tanggal_pembayaran' => '2026-06-10',
            'is_verified' => false,
        ]);

        return [$user, $pembayaran];
    }

    public function test_verifikasi_pembayaran_membuat_jurnal_otomatis(): void
    {
        $tenant = $this->createRuntimeTenant('auto', 'Pesantren Auto');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        [$user, $pembayaran] = $this->buatPembayaran(150000);

        app(PembayaranService::class)->verifikasiPembayaran($pembayaran, $user->id);

        $jurnal = Jurnal::where('ref_type', 'Pembayaran')->where('ref_id', (string) $pembayaran->id)->first();

        $this->assertNotNull($jurnal, 'Pembayaran terverifikasi harus menghasilkan jurnal otomatis.');
        $this->assertSame('syahriyah', $jurnal->tipe_sumber);
        $this->assertEqualsWithDelta(150000, (float) $jurnal->baris()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(150000, (float) $jurnal->baris()->sum('kredit'), 0.01);
    }

    public function test_auto_jurnal_idempoten_terhadap_event_ganda(): void
    {
        $tenant = $this->createRuntimeTenant('auto', 'Pesantren Auto2');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        [$user, $pembayaran] = $this->buatPembayaran(90000);
        $pembayaran->update(['is_verified' => true, 'verified_by' => $user->id, 'verified_at' => now()]);

        $listener = app(PostingPembayaranListener::class);
        $listener->handle(new PembayaranDiverifikasi($pembayaran->fresh(), $user->id));
        $listener->handle(new PembayaranDiverifikasi($pembayaran->fresh(), $user->id));

        $this->assertSame(1, Jurnal::where('ref_type', 'Pembayaran')->count(), 'Event ganda tidak boleh menggandakan jurnal.');
    }

    public function test_keuangan_tetap_jalan_tanpa_coa(): void
    {
        // COA TIDAK di-seed → listener menahan diri, pembayaran tetap terverifikasi.
        $tenant = $this->createRuntimeTenant('auto', 'Pesantren Auto3');
        tenancy()->initialize($tenant);

        [$user, $pembayaran] = $this->buatPembayaran(50000);

        $hasil = app(PembayaranService::class)->verifikasiPembayaran($pembayaran, $user->id);

        $this->assertTrue((bool) $hasil->is_verified);
        $this->assertSame(0, Jurnal::count(), 'Tanpa COA, tidak ada jurnal — tapi pembayaran tidak gagal.');
    }
}
