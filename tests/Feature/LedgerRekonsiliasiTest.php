<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Models\Tagihan;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\LedgerReconciliationService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Blueprint §16 — rekonsiliasi Ledger. Membuktikan: transaksi terverifikasi yang
 * dibuat TANPA event (drift, mis. jalur lama / COA telat) terdeteksi tanpa jurnal,
 * dan --repost menyembuhkannya secara idempoten (listener dedup by ref).
 */
class LedgerRekonsiliasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('laporan_jasa_dan_barang')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_deteksi_dan_repost_jurnal_hilang_idempoten(): void
    {
        $tenant = $this->createRuntimeTenant('rekon', 'Pesantren Rekon');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();

        $userId = (int) User::withoutGlobalScopes()->create([
            'name' => 'Bendahara', 'email' => 'rekon@uji.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ])->id;

        $santri = Santri::create([
            'nis' => 'RK-001', 'nama' => 'Santri Rekon', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);

        // Pembayaran terverifikasi DIBUAT LANGSUNG (tanpa PembayaranService) → tak ada event → tak ada jurnal.
        $tagihan = Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'SPP',
            'nominal' => 150000, 'nominal_akhir' => 150000, 'status' => 'lunas',
            'jatuh_tempo' => now()->addDays(7)->toDateString(),
        ]);
        $pembayaran = Pembayaran::create([
            'tagihan_id' => $tagihan->id, 'santri_id' => $santri->id, 'nominal' => 150000,
            'metode' => 'tunai', 'tanggal_pembayaran' => now()->toDateString(),
            'is_verified' => true, 'verified_by' => $userId, 'verified_at' => now(),
        ]);

        // TabunganTransaksi DIBUAT LANGSUNG (tanpa TabunganService) → tak ada event → tak ada jurnal.
        $tabungan = Tabungan::create(['santri_id' => $santri->id, 'saldo' => 50000]);
        TabunganTransaksi::create([
            'tabungan_id' => $tabungan->id, 'tipe' => 'setor', 'nominal' => 50000,
            'saldo_sebelum' => 0, 'saldo_sesudah' => 50000,
            'tanggal' => now()->toDateString(), 'created_by' => $userId,
        ]);

        $svc = app(LedgerReconciliationService::class);

        // 1) DETEKSI saja → 2 drift terlihat, NOL jurnal terbentuk.
        $pra = $svc->auditJurnalHilang(false);
        $this->assertSame(1, $pra['pembayaran_hilang']);
        $this->assertSame(1, $pra['tabungan_hilang']);
        $this->assertSame(0, $pra['reposted']);
        $this->assertSame(0, Jurnal::count(), 'Deteksi tidak boleh menulis jurnal.');

        // 2) RE-POST → 2 jurnal pasangan terbentuk lewat listener.
        $pulih = $svc->auditJurnalHilang(true);
        $this->assertSame(2, $pulih['reposted']);
        $this->assertTrue(
            Jurnal::where('ref_type', 'Pembayaran')->where('ref_id', (string) $pembayaran->id)->exists(),
            'Jurnal pembayaran harus ter-repost.'
        );
        $this->assertTrue(
            Jurnal::where('ref_type', 'TabunganTransaksi')->exists(),
            'Jurnal tabungan harus ter-repost.'
        );

        // 3) IDEMPOTEN → jalan lagi: nol drift, nol jurnal baru (dedup by ref).
        $jumlahJurnal = Jurnal::count();
        $ulang = $svc->auditJurnalHilang(true);
        $this->assertSame(0, $ulang['pembayaran_hilang'] + $ulang['tabungan_hilang'], 'Drift harus nol setelah repost.');
        $this->assertSame($jumlahJurnal, Jurnal::count(), 'Repost ulang tak boleh menggandakan jurnal.');

        tenancy()->end();
    }

    private function seedTenantRow(Tenant $tenant): void
    {
        \Illuminate\Support\Facades\DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            [
                'name' => $tenant->name, 'is_active' => $tenant->is_active,
                'data' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
            ],
        );
    }
}
