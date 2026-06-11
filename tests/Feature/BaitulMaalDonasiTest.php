<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Keuangan\Models\Donasi;
use Modules\Keuangan\Models\ProgramDonasi;
use Modules\Keuangan\Services\DonasiService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\SumberDana;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * 2c — Baitul Maal: donasi publik manual-transfer + konfirmasi bendahara.
 */
class BaitulMaalDonasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('donasi')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'donasi',
            'program_donasi',
            'jurnal_baris',
            'jurnal',
            'settings',
            'domains',
            'tenants',
        ]);

        RateLimiter::clear('ppdb-submit');
    }

    /** Tenant + LedgerSeeder + 1 program donasi aktif (default dana 05 zakat/infak). */
    private function siapkanTenantDenganProgram(string $id, string $kodeDana = '05'): array
    {
        // createRuntimeTenant — pipeline tenancy membuat DB tenant fisik
        // (createTestTenant insert mentah → DB tenant tidak pernah dibuat).
        $tenant = $this->createRuntimeTenant(substr(str_replace('-', '', $id), 0, 6), 'Pesantren '.$id, $id.'.localhost');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant); // FK settings/users/santri → tenants di DB tenant
        (new LedgerSeeder)->run();

        $sumberDana = SumberDana::where('kode_dana', $kodeDana)->firstOrFail();

        $program = ProgramDonasi::create([
            'nama' => 'Program '.$id,
            'deskripsi' => 'Program donasi uji',
            'sumber_dana_id' => $sumberDana->id,
            'aktif' => true,
        ]);

        return [$tenant, $program, $sumberDana];
    }

    private function postDonasi(string $host, array $data)
    {
        return $this->call('POST', 'http://'.$host.'/donasi', $data, [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
    }

    public function test_donasi_publik_tersimpan_menunggu_transfer(): void
    {
        [, $program] = $this->siapkanTenantDenganProgram('bm-pub');

        $response = $this->postDonasi('bm-pub.localhost', [
            'program_donasi_id' => $program->id,
            'nama_donatur' => 'Hamba Allah',
            'kontak' => '081234567890',
            'nominal' => 250000,
            'pesan' => 'Semoga berkah',
        ]);

        $response->assertRedirect();

        $donasi = Donasi::firstOrFail();
        $this->assertSame(Donasi::STATUS_MENUNGGU, $donasi->status);
        $this->assertSame('250000.00', (string) $donasi->nominal);
        $this->assertNotNull($donasi->client_ref);
        $this->assertNull($donasi->jurnal_id);

        // Halaman terima kasih menampilkan instruksi rekening tenant.
        Setting::updateOrCreate(['key' => 'baitulmaal.rekening'], ['value' => 'BSI 7211223344 a.n. Baitul Maal']);
        $halaman = $this->call('GET', 'http://bm-pub.localhost/donasi/terima-kasih/'.$donasi->client_ref, [], [], [], [
            'HTTP_HOST' => 'bm-pub.localhost',
            'SERVER_NAME' => 'bm-pub.localhost',
        ]);
        $halaman->assertOk();
        $halaman->assertSee('BSI 7211223344');
    }

    public function test_konfirmasi_membuat_jurnal_seimbang_sumber_dana_benar_dan_idempoten(): void
    {
        [, $program, $sumberDana] = $this->siapkanTenantDenganProgram('bm-konf', '05');
        $this->seedTenantRow(tenancy()->tenant);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Bendahara',
            'email' => 'bendahara@bm-konf.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        $donasi = Donasi::create([
            'program_donasi_id' => $program->id,
            'nama_donatur' => 'Donatur Uji',
            'nominal' => 500000,
            'status' => Donasi::STATUS_MENUNGGU,
            'client_ref' => (string) str()->uuid(),
        ]);

        $service = app(DonasiService::class);
        $hasil = $service->konfirmasi($donasi, $user->id);
        $hasil2 = $service->konfirmasi($donasi->fresh(), $user->id); // konfirmasi 2x

        $this->assertSame(Donasi::STATUS_DIKONFIRMASI, $hasil->status);
        $this->assertNotNull($hasil->nomor_kuitansi);
        $this->assertSame($hasil->nomor_kuitansi, $hasil2->nomor_kuitansi);

        $this->assertSame(1, Jurnal::where('ref_type', 'Donasi')->count(), 'Konfirmasi 2x harus tetap 1 jurnal.');

        $jurnal = Jurnal::where('ref_type', 'Donasi')->where('ref_id', (string) $donasi->id)->firstOrFail();
        $this->assertEqualsWithDelta(500000, (float) $jurnal->baris()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(500000, (float) $jurnal->baris()->sum('kredit'), 0.01);
        $this->assertSame(
            [$sumberDana->id],
            $jurnal->baris()->distinct()->pluck('sumber_dana_id')->all(),
            'Semua baris jurnal harus memakai sumber dana program.'
        );

        // Kredit jatuh ke akun penerimaan zakat/infak (4-4000) untuk dana 05.
        $barisKredit = $jurnal->baris()->where('kredit', '>', 0)->firstOrFail();
        $this->assertSame('4-4000', $barisKredit->akun()->value('kode'));
    }

    public function test_tolak_donasi_tidak_membuat_jurnal_dan_tak_bisa_dikonfirmasi(): void
    {
        [, $program] = $this->siapkanTenantDenganProgram('bm-tolak');

        $donasi = Donasi::create([
            'program_donasi_id' => $program->id,
            'nama_donatur' => 'Donatur Batal',
            'nominal' => 100000,
            'status' => Donasi::STATUS_MENUNGGU,
        ]);

        app(DonasiService::class)->tolak($donasi, null);

        $this->assertSame(Donasi::STATUS_DITOLAK, $donasi->fresh()->status);
        $this->assertSame(0, Jurnal::where('ref_type', 'Donasi')->count());

        $this->expectException(InvalidArgumentException::class);
        app(DonasiService::class)->konfirmasi($donasi->fresh(), null);
    }

    public function test_upload_bukti_html_ditolak_pdf_diterima(): void
    {
        Storage::fake('public');
        [, $program] = $this->siapkanTenantDenganProgram('bm-bukti');

        $donasi = Donasi::create([
            'program_donasi_id' => $program->id,
            'nama_donatur' => 'Donatur Bukti',
            'nominal' => 75000,
            'status' => Donasi::STATUS_MENUNGGU,
            'client_ref' => (string) str()->uuid(),
        ]);

        $url = 'http://bm-bukti.localhost/donasi/'.$donasi->client_ref.'/bukti';
        $server = ['HTTP_HOST' => 'bm-bukti.localhost', 'SERVER_NAME' => 'bm-bukti.localhost'];

        // HTML (stored-XSS) DITOLAK.
        $jahat = $this->call('POST', $url, [], [], [
            'bukti' => UploadedFile::fake()->createWithContent('bukti.html', '<script>alert(1)</script>'),
        ], $server);
        $jahat->assertSessionHasErrors('bukti');
        $this->assertNull($donasi->fresh()->bukti_path);

        // PDF diterima, tersimpan di direktori sensitif 'donasi'.
        $sah = $this->call('POST', $url, [], [], [
            'bukti' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
        ], $server);
        $sah->assertRedirect();
        $path = $donasi->fresh()->bukti_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('donasi/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_form_publik_dithrottle(): void
    {
        [, $program] = $this->siapkanTenantDenganProgram('bm-throttle');

        $payload = [
            'program_donasi_id' => $program->id,
            'nama_donatur' => 'Spammer',
            'nominal' => 10000,
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postDonasi('bm-throttle.localhost', $payload)->assertRedirect();
        }

        $this->postDonasi('bm-throttle.localhost', $payload)->assertStatus(429);
    }

    public function test_kuitansi_hanya_untuk_donasi_dikonfirmasi(): void
    {
        [, $program] = $this->siapkanTenantDenganProgram('bm-kw');
        $this->seedTenantRow(tenancy()->tenant);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Bendahara KW',
            'email' => 'kw@bm-kw.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        Permission::findOrCreate('view_keuangan');
        $user->givePermissionTo('view_keuangan');

        $donasi = Donasi::create([
            'program_donasi_id' => $program->id,
            'nama_donatur' => 'Donatur KW',
            'nominal' => 300000,
            'status' => Donasi::STATUS_MENUNGGU,
        ]);

        $server = ['HTTP_HOST' => 'bm-kw.localhost', 'SERVER_NAME' => 'bm-kw.localhost'];
        $url = 'http://bm-kw.localhost/keuangan/donasi/'.$donasi->id.'/kuitansi';

        // Belum dikonfirmasi → 404.
        $this->actingAs($user)->call('GET', $url, [], [], [], $server)->assertNotFound();

        app(DonasiService::class)->konfirmasi($donasi, $user->id);

        $ok = $this->actingAs($user)->call('GET', $url, [], [], [], $server);
        $ok->assertOk();
        $ok->assertSee($donasi->fresh()->nomor_kuitansi);
        $ok->assertSee('Donatur KW');

        // User login TANPA hak keuangan (mis. akun santri/wali) → 403,
        // tidak bisa enumerasi PII donatur via id sekuensial.
        $polos = User::withoutGlobalScopes()->create([
            'name' => 'Santri Polos',
            'email' => 'santri-polos@bm-kw.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $this->actingAs($polos)->call('GET', $url, [], [], [], $server)->assertForbidden();
    }

    public function test_dua_tenant_program_dan_rekening_terpisah(): void
    {
        [$tenantA] = $this->siapkanTenantDenganProgram('bm-isoa');
        Setting::updateOrCreate(['key' => 'baitulmaal.rekening'], ['value' => 'Rekening Tenant A']);
        tenancy()->end();

        [$tenantB] = $this->siapkanTenantDenganProgram('bm-isob');
        Setting::updateOrCreate(['key' => 'baitulmaal.rekening'], ['value' => 'Rekening Tenant B']);
        tenancy()->end();

        $halamanA = $this->call('GET', 'http://bm-isoa.localhost/donasi', [], [], [], [
            'HTTP_HOST' => 'bm-isoa.localhost', 'SERVER_NAME' => 'bm-isoa.localhost',
        ]);
        $halamanA->assertOk();
        $halamanA->assertSee('Program bm-isoa');
        $halamanA->assertDontSee('Program bm-isob');

        // Dalam satu proses test, tenancy hasil request A masih aktif —
        // akhiri agar request B re-resolve tenant dari domainnya sendiri.
        tenancy()->end();

        $halamanB = $this->call('GET', 'http://bm-isob.localhost/donasi', [], [], [], [
            'HTTP_HOST' => 'bm-isob.localhost', 'SERVER_NAME' => 'bm-isob.localhost',
        ]);
        $halamanB->assertOk();
        $halamanB->assertSee('Program bm-isob');
        $halamanB->assertDontSee('Program bm-isoa');

        // Rekening per tenant (policy-first) — Setting key sama, nilai beda.
        tenancy()->initialize($tenantA);
        $this->assertSame('Rekening Tenant A', Setting::getTenantStringValue('baitulmaal.rekening'));
        tenancy()->end();
        tenancy()->initialize($tenantB);
        $this->assertSame('Rekening Tenant B', Setting::getTenantStringValue('baitulmaal.rekening'));
    }
}
