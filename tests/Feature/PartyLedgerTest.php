<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Services\PartyLedgerService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Party Ledger (buku pembantu per pihak). Menguji passthrough party di
 * JurnalService + saldo/daftar/aging PartyLedgerService.
 */
class PartyLedgerTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'akun', 'jurnal', 'jurnal_baris', 'sumber_dana',
        ]);

        // Kolom party_type/party_id baru di jurnal_baris → paksa fresh bila belum ada.
        if (! Schema::connection('pgsql')->hasColumn('jurnal_baris', 'party_type')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();
        $this->seedTenantRow($tenant);
    }

    private function santri(string $nama): Santri
    {
        return Santri::create([
            'nis' => 'PL-'.str()->random(5), 'nama' => $nama,
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    private function akun(string $kode): int
    {
        return (int) Akun::query()->where('kode', $kode)->value('id');
    }

    private function postPiutang(Santri $s, float $nominal, ?string $tanggal = null): void
    {
        app(JurnalService::class)->postJurnal([
            'tanggal' => $tanggal ?? now()->toDateString(),
            'deskripsi' => 'Piutang SPP',
            'tipe_sumber' => 'manual',
            'allow_duplicate' => true,
            'baris' => [
                ['akun_id' => $this->akun('1-1000'), 'party_type' => Santri::class, 'party_id' => $s->id, 'debit' => $nominal, 'kredit' => 0],
                ['akun_id' => $this->akun('4-1000'), 'debit' => 0, 'kredit' => $nominal],
            ],
        ]);
    }

    public function test_saldo_dan_daftar_per_santri(): void
    {
        $this->boot('pl1');
        $andi = $this->santri('Andi');
        $budi = $this->santri('Budi');
        $this->postPiutang($andi, 500000);
        $this->postPiutang($andi, 300000);
        $this->postPiutang($budi, 200000);

        $svc = app(PartyLedgerService::class);
        $this->assertEqualsWithDelta(800000, $svc->saldo(Santri::class, $andi->id), 0.01);
        $this->assertEqualsWithDelta(200000, $svc->saldo(Santri::class, $budi->id), 0.01);

        $daftar = $svc->daftarByTipe(Santri::class);
        $this->assertCount(2, $daftar);
        $this->assertEqualsWithDelta(800000, $daftar[$andi->id], 0.01);
        tenancy()->end();
    }

    public function test_aging_per_umur(): void
    {
        $this->boot('pl2');
        $s = $this->santri('Citra');
        $this->postPiutang($s, 100000, now()->toDateString());          // 0-30
        $this->postPiutang($s, 50000, now()->subDays(45)->toDateString());  // 31-60
        $this->postPiutang($s, 25000, now()->subDays(120)->toDateString()); // >90

        $aging = app(PartyLedgerService::class)->aging(Santri::class, $s->id);
        $this->assertEqualsWithDelta(100000, $aging['0-30'], 0.01);
        $this->assertEqualsWithDelta(50000, $aging['31-60'], 0.01);
        $this->assertEqualsWithDelta(25000, $aging['>90'], 0.01);
        tenancy()->end();
    }

    public function test_pelunasan_menetralkan_saldo(): void
    {
        $this->boot('pl3');
        $s = $this->santri('Dina');
        $this->postPiutang($s, 100000);
        // Pelunasan: Debit Kas, Kredit Piutang(party=santri) → saldo party 0.
        app(JurnalService::class)->postJurnal([
            'tanggal' => now()->toDateString(), 'deskripsi' => 'Pelunasan', 'tipe_sumber' => 'manual',
            'allow_duplicate' => true,
            'baris' => [
                ['akun_id' => $this->akun('4-1000'), 'debit' => 100000, 'kredit' => 0],
                ['akun_id' => $this->akun('1-1000'), 'party_type' => Santri::class, 'party_id' => $s->id, 'debit' => 0, 'kredit' => 100000],
            ],
        ]);

        $this->assertEqualsWithDelta(0, app(PartyLedgerService::class)->saldo(Santri::class, $s->id), 0.01);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('plA');
        $s = $this->santri('Eka');
        $this->postPiutang($s, 100000);
        $this->assertCount(1, app(PartyLedgerService::class)->daftarByTipe(Santri::class));
        tenancy()->end();

        $this->boot('plB');
        $this->assertCount(0, app(PartyLedgerService::class)->daftarByTipe(Santri::class));
        tenancy()->end();
    }
}
