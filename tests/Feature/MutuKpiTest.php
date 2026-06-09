<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Core\Exceptions\PersetujuanBelumLengkapException;
use Modules\Core\Models\ApprovalRule;
use Modules\Mutu\Models\MutuSasaran;
use Modules\Mutu\Models\MutuTemuan;
use Modules\Mutu\Services\MutuKpiService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G3-17 — SPMI/Quality: KPI capaian, state-machine temuan (Non-Conformance) +
 * gerbang F1, SKM, ringkasan dashboard, isolasi tenant.
 */
class MutuKpiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users',
            'mutu_sasaran', 'mutu_inspeksi', 'mutu_temuan', 'mutu_skm',
            'approval_rule', 'approval_request', 'audit_log',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_capaian_kpi_dan_catat_realisasi(): void
    {
        $this->boot('kpi1');
        $s = MutuSasaran::create(['nama' => 'Kelulusan', 'area' => 'akademik', 'target' => 100, 'realisasi' => 0]);
        $this->assertEqualsWithDelta(0, $s->capaian(), 0.01);

        app(MutuKpiService::class)->catatRealisasi($s, 80);
        $this->assertEqualsWithDelta(80, $s->fresh()->capaian(), 0.01);
        tenancy()->end();
    }

    public function test_temuan_state_machine_lengkap(): void
    {
        $this->boot('kpi2');
        $svc = app(MutuKpiService::class);
        $t = $svc->buatTemuan(['judul' => 'Absensi tahfidz kurang', 'severity' => 'major']);
        $this->assertSame('terbuka', $t->status);
        $this->assertSame(2, $t->severity_level); // derive dari severity

        $t = $svc->mulaiTindakan($t, 'Perbaikan jadwal setoran');
        $this->assertSame('tindakan', $t->status);
        $this->assertSame('Perbaikan jadwal setoran', $t->tindakan_korektif);

        $t = $svc->ajukanVerifikasi($t);
        $this->assertSame('verifikasi', $t->status);

        $u = $this->user();
        $t = $svc->tutup($t, $u->id);
        $this->assertSame('tutup', $t->status);
        $this->assertSame($u->id, $t->diverifikasi_oleh);
        tenancy()->end();
    }

    public function test_transisi_ilegal_ditolak(): void
    {
        $this->boot('kpi3');
        $svc = app(MutuKpiService::class);
        $t = $svc->buatTemuan(['judul' => 'X', 'severity' => 'minor']);

        $this->expectException(InvalidArgumentException::class);
        $svc->tutup($t, $this->user()->id); // terbuka → tutup ILEGAL
        tenancy()->end();
    }

    public function test_tutup_terhalang_approval_wajib_severity_tinggi(): void
    {
        $this->boot('kpi4');
        // Aturan: temuan severity_level >= 2 WAJIB disetujui sebelum ditutup.
        ApprovalRule::create([
            'approvable_type' => MutuTemuan::class,
            'nama' => 'Tutup temuan major', 'field' => 'severity_level',
            'operator' => '>=', 'nilai' => 2, 'approver_role' => 'super_admin',
            'mode' => 'wajib', 'urutan' => 1, 'aktif' => true,
        ]);
        $svc = app(MutuKpiService::class);
        $t = $svc->buatTemuan(['judul' => 'Kritis', 'severity' => 'kritis']); // level 3 ≥ 2
        $svc->mulaiTindakan($t, 'tindakan');
        $svc->ajukanVerifikasi($t->fresh());

        $this->expectException(PersetujuanBelumLengkapException::class);
        $svc->tutup($t->fresh(), $this->user()->id); // terhalang gerbang F1
        tenancy()->end();
    }

    public function test_tutup_minor_tidak_terhalang_aturan_severity_tinggi(): void
    {
        $this->boot('kpi5');
        ApprovalRule::create([
            'approvable_type' => MutuTemuan::class,
            'nama' => 'Tutup temuan major', 'field' => 'severity_level',
            'operator' => '>=', 'nilai' => 2, 'approver_role' => 'super_admin',
            'mode' => 'wajib', 'urutan' => 1, 'aktif' => true,
        ]);
        $svc = app(MutuKpiService::class);
        $t = $svc->buatTemuan(['judul' => 'Sepele', 'severity' => 'minor']); // level 1 < 2 → tak terpicu
        $svc->mulaiTindakan($t, 'beres');
        $svc->ajukanVerifikasi($t->fresh());
        $t = $svc->tutup($t->fresh(), $this->user()->id);

        $this->assertSame('tutup', $t->status);
        tenancy()->end();
    }

    public function test_tutup_berhasil_setelah_disetujui_happy_path(): void
    {
        $this->boot('kpi8');
        ApprovalRule::create([
            'approvable_type' => MutuTemuan::class,
            'nama' => 'Tutup temuan major', 'field' => 'severity_level',
            'operator' => '>=', 'nilai' => 2, 'approver_role' => 'super_admin',
            'mode' => 'wajib', 'urutan' => 1, 'aktif' => true,
        ]);
        $svc = app(MutuKpiService::class);
        $approver = $this->user();

        $t = $svc->buatTemuan(['judul' => 'Kritis', 'severity' => 'kritis']);
        $svc->mulaiTindakan($t, 'tindakan');
        // ajukanVerifikasi MEMBUAT ApprovalRequest 'menunggu' (F1).
        $svc->ajukanVerifikasi($t->fresh(), $approver->id);
        $req = $t->fresh()->approvalMenunggu();
        $this->assertNotNull($req, 'ajukanVerifikasi harus membuat permintaan persetujuan.');

        // Penyetuju menyetujui via engine F1.
        app(\Modules\Core\Services\ApprovalService::class)->setujui($req, $approver->id);

        // Setelah disetujui, penutupan BERHASIL.
        $t = $svc->tutup($t->fresh(), $approver->id);
        $this->assertSame('tutup', $t->status);
        tenancy()->end();
    }

    public function test_severity_tak_bisa_didowngrade_setelah_terbuka(): void
    {
        $this->boot('kpi9');
        $svc = app(MutuKpiService::class);
        $t = $svc->buatTemuan(['judul' => 'Kritis', 'severity' => 'kritis']);
        $svc->mulaiTindakan($t, 'tindakan'); // keluar dari 'terbuka'

        // Upaya downgrade severity (vektor bypass gerbang F1) HARUS ditolak.
        $this->expectException(InvalidArgumentException::class);
        $t->fresh()->update(['severity' => 'minor']);
        tenancy()->end();
    }

    public function test_skm_dan_ringkasan(): void
    {
        $this->boot('kpi6');
        $svc = app(MutuKpiService::class);
        MutuSasaran::create(['nama' => 'A', 'area' => 'layanan', 'target' => 100, 'realisasi' => 90]);
        $svc->buatTemuan(['judul' => 'T1', 'severity' => 'minor']);
        $svc->catatSkm(['skor' => 4, 'aspek' => 'layanan']);
        $svc->catatSkm(['skor' => 5, 'aspek' => 'layanan']);

        $r = $svc->ringkasan();
        $this->assertEqualsWithDelta(90, $r['sasaran']['capaian_rata'], 0.01);
        $this->assertSame(1, $r['temuan']['terbuka']);
        $this->assertEqualsWithDelta(4.5, $r['skm']['rata'], 0.01);
        $this->assertEqualsWithDelta(90, $r['skm']['indeks'], 0.01); // 4.5/5*100
        tenancy()->end();
    }

    public function test_skm_skor_invalid_ditolak(): void
    {
        $this->boot('kpi7');
        $this->expectException(InvalidArgumentException::class);
        app(MutuKpiService::class)->catatSkm(['skor' => 9]);
        tenancy()->end();
    }

    public function test_isolasi_tenant_mutu(): void
    {
        $this->boot('kpiA');
        MutuSasaran::create(['nama' => 'A', 'area' => 'umum', 'target' => 10, 'realisasi' => 1]);
        $this->assertSame(1, MutuSasaran::query()->count());
        tenancy()->end();

        $this->boot('kpiB');
        $this->assertSame(0, MutuSasaran::query()->count(), 'Sasaran tenant lain tak boleh terlihat.');
        tenancy()->end();
    }

    private function user(): \App\Models\User
    {
        return \App\Models\User::withoutGlobalScopes()->create([
            'name' => 'U', 'email' => 'u'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
    }
}
