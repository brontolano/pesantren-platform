<?php

namespace Tests\Feature;

use Stancl\Tenancy\Database\Models\Domain;
use App\Services\DomainVerificationService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Verifikasi kepemilikan domain tenant — TXT DNS & fallback .well-known. */
class DomainVerificationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users']);
        if (! \Illuminate\Support\Facades\Schema::connection('pgsql')->hasColumn('domains', 'verification_token')) {
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** Stub lookup agar deterministik (tanpa DNS/HTTP nyata). */
    private function svc(array $txt = [], ?string $wellKnown = null): DomainVerificationService
    {
        return new class($txt, $wellKnown) extends DomainVerificationService
        {
            public function __construct(private array $txt, private ?string $wk)
            {
            }

            protected function txtRecords(string $domain): array
            {
                return $this->txt;
            }

            protected function fetchWellKnown(string $domain): ?string
            {
                return $this->wk;
            }
        };
    }

    private function domain(): Domain
    {
        $tenant = $this->createRuntimeTenant('dvf', 'Pesantren DV');

        return $tenant->domains()->firstOrCreate(['domain' => 'pondokku.sch.id']);
    }

    public function test_token_diterbitkan_sekali_dan_stabil(): void
    {
        $domain = $this->domain();
        $svc = $this->svc();

        $t1 = $svc->issueToken($domain);
        $t2 = $svc->issueToken($domain->fresh());
        $this->assertSame($t1, $t2);
        $this->assertSame(40, strlen($t1));
        $this->assertNull($domain->fresh()->verified_at);
    }

    public function test_verifikasi_via_txt_dan_via_well_known(): void
    {
        $domain = $this->domain();
        $token = $this->svc()->issueToken($domain);

        // Bukti salah → tetap belum terverifikasi.
        $gagal = $this->svc(txt: ['pesantren-verify=salah'], wellKnown: 'salah')->verify($domain->fresh());
        $this->assertFalse($gagal['verified']);
        $this->assertNull($domain->fresh()->verified_at);

        // TXT benar → verified (method dns-txt).
        $ok = $this->svc(txt: ["pesantren-verify={$token}"])->verify($domain->fresh());
        $this->assertTrue($ok['verified']);
        $this->assertSame('dns-txt', $ok['method']);
        $this->assertNotNull($domain->fresh()->verified_at);

        // Fallback .well-known utk domain kedua.
        $tenant = \App\Models\Tenant::find($domain->tenant_id);
        $d2 = $tenant->domains()->firstOrCreate(['domain' => 'kedua.sch.id']);
        $token2 = $this->svc()->issueToken($d2);
        $ok2 = $this->svc(wellKnown: "  {$token2}\n")->verify($d2->fresh());
        $this->assertTrue($ok2['verified']);
        $this->assertSame('well-known', $ok2['method']);
    }

    public function test_tanpa_token_tidak_bisa_verifikasi(): void
    {
        $domain = $this->domain();
        $hasil = $this->svc(txt: ['pesantren-verify=apapun'])->verify($domain);
        $this->assertFalse($hasil['verified']);
    }
}
