<?php

namespace Tests\Feature;

use App\Filament\Pages\JurnalManual;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\JurnalService;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Anti double-post jurnal manual: guard idempotensi di JurnalService +
 * token per-render form di halaman JurnalManual. WP UANG.
 */
class JurnalManualIdempotencyTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('kebijakan_kehalalan')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array<int,array<string,mixed>> Dua baris seimbang Kas/Pendapatan. */
    private function baris(float $nominal = 50000): array
    {
        return [
            ['akun_id' => Akun::where('kode', '1-1000')->value('id'), 'debit' => $nominal, 'kredit' => 0],
            ['akun_id' => Akun::where('kode', '4-1000')->value('id'), 'debit' => 0, 'kredit' => $nominal],
        ];
    }

    public function test_post_keyless_ditolak_guard(): void
    {
        $this->boot('jmg1');
        $this->expectException(InvalidArgumentException::class);
        app(JurnalService::class)->postJurnal([
            'tanggal' => '2026-06-10', 'deskripsi' => 'Tanpa kunci', 'tipe_sumber' => 'manual',
            'baris' => $this->baris(),
        ]);
        tenancy()->end();
    }

    public function test_allow_duplicate_eksplisit_membolehkan_keyless(): void
    {
        $this->boot('jmg2');
        $svc = app(JurnalService::class);
        $payload = [
            'tanggal' => '2026-06-10', 'deskripsi' => 'Sengaja boleh ganda', 'tipe_sumber' => 'manual',
            'allow_duplicate' => true, 'baris' => $this->baris(),
        ];
        $svc->postJurnal($payload);
        $svc->postJurnal($payload); // identik tapi opt-in → memang dua jurnal
        $this->assertSame(2, Jurnal::where('tipe_sumber', 'manual')->count());
        tenancy()->end();
    }

    public function test_token_sama_dedup_jadi_satu_jurnal(): void
    {
        $this->boot('jmg3');
        $svc = app(JurnalService::class);
        $payload = [
            'tanggal' => '2026-06-10', 'deskripsi' => 'Jurnal manual', 'tipe_sumber' => 'manual',
            'ref_type' => 'jurnal_manual', 'ref_id' => 'token-abc-123', 'baris' => $this->baris(),
        ];
        $a = $svc->postJurnal($payload);
        $b = $svc->postJurnal($payload); // submit kembar (token sama) → jurnal yang sama
        $this->assertSame($a->id, $b->id, 'Submit ulang token sama tak boleh menggandakan.');
        $this->assertSame(1, Jurnal::where('tipe_sumber', 'manual')->count());
        tenancy()->end();
    }

    public function test_token_beda_jurnal_terpisah(): void
    {
        $this->boot('jmg4');
        $svc = app(JurnalService::class);
        $base = ['tanggal' => '2026-06-10', 'deskripsi' => 'Jurnal manual', 'tipe_sumber' => 'manual', 'baris' => $this->baris()];
        $svc->postJurnal($base + ['ref_type' => 'jurnal_manual', 'ref_id' => 'tok-1']);
        $svc->postJurnal($base + ['ref_type' => 'jurnal_manual', 'ref_id' => 'tok-2']);
        $this->assertSame(2, Jurnal::where('tipe_sumber', 'manual')->count(), 'Token beda = jurnal berbeda.');
        tenancy()->end();
    }

    public function test_dana_kebajikan_refid_dedup_saldo_tetap_benar(): void
    {
        $this->boot('jmg6');
        $svc = app(\Modules\Ledger\Services\DanaKebajikanService::class);
        // Pola AMAN untuk caller (UI ke depan WAJIB ikut): kirim refId stabil dari
        // dokumen sumber → submit kembar dedup, saldo tak menggandakan.
        $svc->terima(500000, '90', refId: 'KB-001');
        $svc->terima(500000, '90', refId: 'KB-001'); // kembar (retry/double-click)
        $this->assertEqualsWithDelta(500000, $svc->saldo(), 0.01, 'RefId sama tak boleh menggandakan saldo dana kebajikan.');
        $this->assertSame(1, Jurnal::where('tipe_sumber', 'kebajikan')->count());
        tenancy()->end();
    }

    public function test_halaman_jurnal_manual_token_unik_dan_tertambat(): void
    {
        $this->boot('jmg5');
        $user = User::withoutGlobalScopes()->create([
            'name' => 'Bendahara', 'email' => 'bendahara@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $user->assignRole('bendahara'); // punya manage_ledger
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $component = Livewire::test(JurnalManual::class);
        $token = $component->get('idempotencyToken');
        $this->assertNotEmpty($token, 'mount() harus menghasilkan token idempotensi.');

        // Mount kedua → token berbeda (regenerasi per render).
        $token2 = Livewire::test(JurnalManual::class)->get('idempotencyToken');
        $this->assertNotSame($token, $token2);

        // Submit sah → jurnal tertambat ke token form, tipe manual.
        $component->fillForm([
            'tanggal' => '2026-06-10',
            'deskripsi' => 'Entri manual uji',
            'baris' => $this->baris(75000),
        ])->call('simpan');

        $jurnal = Jurnal::where('ref_type', 'jurnal_manual')->where('ref_id', $token)->first();
        $this->assertNotNull($jurnal, 'Jurnal manual harus tertambat ke token form.');
        $this->assertSame(1, Jurnal::where('tipe_sumber', 'manual')->count());

        // Token diperbarui setelah sukses → entri berikutnya unik (bukan dianggap kembar).
        $this->assertNotSame($token, $component->get('idempotencyToken'));

        // Request kembar dengan token LAMA (mis. retry) → tetap jurnal yang sama.
        $kembar = app(JurnalService::class)->postJurnal([
            'tanggal' => '2026-06-10', 'deskripsi' => 'Entri manual uji', 'tipe_sumber' => 'manual',
            'ref_type' => 'jurnal_manual', 'ref_id' => $token, 'baris' => $this->baris(75000),
        ]);
        $this->assertSame($jurnal->id, $kembar->id);
        $this->assertSame(1, Jurnal::where('tipe_sumber', 'manual')->count());

        tenancy()->end();
    }
}
