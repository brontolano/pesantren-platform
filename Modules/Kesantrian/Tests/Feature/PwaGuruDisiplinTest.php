<?php

declare(strict_types=1);

namespace Modules\Kesantrian\Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Modules\Kesantrian\Models\EskalasiDisiplin;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-24 — PWA Guru Disiplin API & Vue Pages tests.
 *
 * Tests:
 *   1. Auto-verify ON/OFF
 *   2. Client_ref idempotency
 *   3. Role gating (akses_pwa_guru)
 *   4. poinTerbaru respects masa_berlaku
 *   5. Verification workflow (pencatat ≠ verifikator)
 *   6. Page loads with correct data
 */
class PwaGuruDisiplinTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected Tenant $tenant;

    protected Santri $santri;

    protected JenisPelanggaran $jenis;

    protected User $pencatat;

    protected User $verifikator;

    protected DisiplinService $disiplinService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createRuntimeTenant('wpdisp', 'Pesantren WP-24');
        tenancy()->initialize($this->tenant);

        $this->disiplinService = app(DisiplinService::class);

        // Buat user pencatat (guru, dapat akses PWA)
        $this->pencatat = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bu Ani (Pencatat)',
            'email' => 'pencatat@test.local',
            'password' => bcrypt('password'),
        ]);
        $this->pencatat->givePermissionTo('akses_pwa_guru');

        // Buat user verifikator (musyrif)
        $this->verifikator = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pak Budi (Verifikator)',
            'email' => 'verifikator@test.local',
            'password' => bcrypt('password'),
        ]);
        $this->verifikator->givePermissionTo('akses_pwa_guru');

        // Buat santri
        $this->santri = Santri::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Ahmad Rizki',
            'nis' => '001',
            'status' => 'aktif',
        ]);

        // Buat jenis pelanggaran
        $this->jenis = JenisPelanggaran::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Terlambat Sholat',
            'kategori' => 'ringan',
            'poin' => 10,
            'aktif' => true,
        ]);
    }

    /** Test 1: Auto-verify ketika wajib_verifikasi = false */
    public function test_auto_verify_saat_verifikasi_off(): void
    {
        // Set wajib_verifikasi = false
        Setting::setTenantValue('disiplin.wajib_verifikasi', false);

        $response = $this->actingAs($this->pencatat)
            ->postJson('/api/pwa/guru/disiplin/catatPelanggaran', [
                'santri_id' => $this->santri->id,
                'jenis_pelanggaran_id' => $this->jenis->id,
                'alasan' => 'Terlambat masuk musholla jam 6 pagi tanpa alasan',
                'waktu_kejadian' => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');

        $pelanggaran = PelanggaranSantri::first();
        $this->assertEquals('diverifikasi', $pelanggaran->status);
        $this->assertEquals($this->pencatat->id, $pelanggaran->verifikator_id);
        $this->assertNotNull($pelanggaran->verified_at);
    }

    /** Test 2: Verifikasi 2-lapis ketika wajib_verifikasi = true */
    public function test_verifikasi_2_lapis_saat_verifikasi_on(): void
    {
        // Set wajib_verifikasi = true (default)
        Setting::setTenantValue('disiplin.wajib_verifikasi', true);

        // Catat pelanggaran
        $response = $this->actingAs($this->pencatat)
            ->postJson('/api/pwa/guru/disiplin/catatPelanggaran', [
                'santri_id' => $this->santri->id,
                'jenis_pelanggaran_id' => $this->jenis->id,
                'alasan' => 'Terlambat masuk musholla',
                'waktu_kejadian' => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertStatus(201);

        $pelanggaran = PelanggaranSantri::first();
        $this->assertEquals('dicatat', $pelanggaran->status);
        $this->assertNull($pelanggaran->verifikator_id);
        $this->assertNull($pelanggaran->verified_at);

        // Verifikasi oleh verifikator
        $verifyResponse = $this->actingAs($this->verifikator)
            ->postJson("/api/pwa/guru/disiplin/{$pelanggaran->id}/verifikasi", []);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJsonPath('status', 'success');

        $pelanggaran->refresh();
        $this->assertEquals('diverifikasi', $pelanggaran->status);
        $this->assertEquals($this->verifikator->id, $pelanggaran->verifikator_id);
    }

    /** Test 3: Client_ref idempotency */
    public function test_client_ref_idempotency(): void
    {
        $clientRef = 'test-ref-'.microtime(true);

        // POST pertama
        $response1 = $this->actingAs($this->pencatat)
            ->postJson('/api/pwa/guru/disiplin/catatPelanggaran', [
                'santri_id' => $this->santri->id,
                'jenis_pelanggaran_id' => $this->jenis->id,
                'alasan' => 'Terlambat masuk musholla',
                'client_ref' => $clientRef,
            ]);

        $response1->assertStatus(201);
        $id1 = $response1->json('data.id');

        // POST kedua dengan client_ref sama
        $response2 = $this->actingAs($this->pencatat)
            ->postJson('/api/pwa/guru/disiplin/catatPelanggaran', [
                'santri_id' => $this->santri->id,
                'jenis_pelanggaran_id' => $this->jenis->id,
                'alasan' => 'Terlambat masuk musholla (duplikat)',
                'client_ref' => $clientRef,
            ]);

        $response2->assertStatus(200); // 200, bukan 201
        $response2->assertJsonPath('message', 'Pelanggaran sudah tercatat');
        $id2 = $response2->json('data.id');

        // Harus sama
        $this->assertEquals($id1, $id2);

        // Total pelanggaran tetap 1
        $this->assertEquals(1, PelanggaranSantri::count());
    }

    /** Test 4: Role gating (akses_pwa_guru) */
    public function test_akses_pwa_guru_gate(): void
    {
        $userTanpaAkses = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User Biasa',
            'email' => 'biasa@test.local',
            'password' => bcrypt('password'),
        ]);

        // Catat dengan user tanpa permission
        $response = $this->actingAs($userTanpaAkses)
            ->postJson('/api/pwa/guru/disiplin/catatPelanggaran', [
                'santri_id' => $this->santri->id,
                'jenis_pelanggaran_id' => $this->jenis->id,
                'alasan' => 'Terlambat masuk musholla',
            ]);

        $response->assertStatus(403); // Forbidden

        // Daftar dengan user tanpa permission
        $response2 = $this->actingAs($userTanpaAkses)
            ->getJson('/api/pwa/guru/disiplin/daftar');

        $response2->assertStatus(403);
    }

    /** Test 5: poinTerbaru respects masa_berlaku */
    public function test_poin_terbaru_masa_berlaku_semester(): void
    {
        // Set masa_berlaku = semester (default)
        Setting::setTenantValue('disiplin.masa_berlaku_poin', 'semester');

        // Buat 2 pelanggaran di bulan berbeda (S1 vs S2)
        $pelanggaran1 = PelanggaranSantri::create([
            'tenant_id' => $this->tenant->id,
            'santri_id' => $this->santri->id,
            'jenis_pelanggaran_id' => $this->jenis->id,
            'waktu_kejadian' => now()->copy()->month(3),
            'kronologi' => 'Pelanggaran S1',
            'pencatat_id' => $this->pencatat->id,
            'status' => 'diverifikasi',
            'verifikator_id' => $this->verifikator->id,
            'verified_at' => now(),
        ]);

        $jenis2 = JenisPelanggaran::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Bolos',
            'kategori' => 'sedang',
            'poin' => 25,
            'aktif' => true,
        ]);

        $pelanggaran2 = PelanggaranSantri::create([
            'tenant_id' => $this->tenant->id,
            'santri_id' => $this->santri->id,
            'jenis_pelanggaran_id' => $jenis2->id,
            'waktu_kejadian' => now()->copy()->month(10),
            'kronologi' => 'Pelanggaran S2',
            'pencatat_id' => $this->pencatat->id,
            'status' => 'diverifikasi',
            'verifikator_id' => $this->verifikator->id,
            'verified_at' => now(),
        ]);

        // Jika sekarang bulan 3 (S1), hanya pelanggaran1 yang dihitung
        $poin = $this->disiplinService->totalPoin($this->santri, now()->copy()->month(3));
        $this->assertEquals(10, $poin); // Hanya pelanggaran1 (10 poin)

        // Jika sekarang bulan 10 (S2), hanya pelanggaran2 yang dihitung
        $poin2 = $this->disiplinService->totalPoin($this->santri, now()->copy()->month(10));
        $this->assertEquals(25, $poin2); // Hanya pelanggaran2 (25 poin)
    }

    /** Test 6: Daftar pelanggaran dengan grouping by santri */
    public function test_daftar_pelanggaran_grouping(): void
    {
        // Buat 3 pelanggaran untuk santri yang sama
        for ($i = 0; $i < 3; $i++) {
            PelanggaranSantri::create([
                'tenant_id' => $this->tenant->id,
                'santri_id' => $this->santri->id,
                'jenis_pelanggaran_id' => $this->jenis->id,
                'waktu_kejadian' => now(),
                'kronologi' => "Pelanggaran $i",
                'pencatat_id' => $this->pencatat->id,
                'status' => 'diverifikasi',
                'verifikator_id' => $this->verifikator->id,
                'verified_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->pencatat)
            ->getJson('/api/pwa/guru/disiplin/daftar');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.0.santri_id', $this->santri->id);
        $response->assertJsonPath('data.0.total_pelanggaran', 3);
        $response->assertJsonPath('data.0.poin_bulan_ini', 30); // 3 × 10 poin
    }

    /** Test 7: Verifikasi menolak self-verify */
    public function test_verifikasi_reject_self_verify(): void
    {
        // Catat dengan pencatat
        $pelanggaran = PelanggaranSantri::create([
            'tenant_id' => $this->tenant->id,
            'santri_id' => $this->santri->id,
            'jenis_pelanggaran_id' => $this->jenis->id,
            'waktu_kejadian' => now(),
            'kronologi' => 'Pelanggaran',
            'pencatat_id' => $this->pencatat->id,
            'status' => 'dicatat',
        ]);

        // Coba self-verify (pencatat = verifikator)
        $response = $this->actingAs($this->pencatat)
            ->postJson("/api/pwa/guru/disiplin/{$pelanggaran->id}/verifikasi", []);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $this->assertStringContainsString('Pencatat tidak boleh', $response->json('message'));
    }

    /** Test 8: Form catat page loads dengan data santri & jenis */
    public function test_form_catat_page_loads(): void
    {
        $response = $this->actingAs($this->pencatat)
            ->get('/guru/disiplin/catat');

        $response->assertStatus(200);
        $response->assertViewHas('santriList');
        $response->assertViewHas('jenisList');

        // Verifikasi data ada
        $santriList = $response->props()['santriList'];
        $this->assertCount(1, $santriList);
        $this->assertEquals($this->santri->id, $santriList[0]['id']);
    }

    /** Test 9: Daftar page loads */
    public function test_daftar_page_loads(): void
    {
        $response = $this->actingAs($this->pencatat)
            ->get('/guru/disiplin/daftar');

        $response->assertStatus(200);
    }

    /** Test 10: Poin page loads */
    public function test_poin_page_loads(): void
    {
        $response = $this->actingAs($this->pencatat)
            ->get('/guru/disiplin/poin');

        $response->assertStatus(200);
    }

    /** Test 11: Eskalasi trigger saat verifikasi */
    public function test_eskalasi_trigger_on_verify(): void
    {
        // Set ambang = 10 poin untuk SP1
        Setting::setTenantValue('disiplin.ambang', json_encode([
            ['poin' => 10, 'label' => 'SP1'],
        ]));

        // Catat pelanggaran (10 poin)
        $pelanggaran = PelanggaranSantri::create([
            'tenant_id' => $this->tenant->id,
            'santri_id' => $this->santri->id,
            'jenis_pelanggaran_id' => $this->jenis->id,
            'waktu_kejadian' => now(),
            'kronologi' => 'Pelanggaran',
            'pencatat_id' => $this->pencatat->id,
            'status' => 'dicatat',
        ]);

        // Verifikasi
        $this->actingAs($this->verifikator)
            ->postJson("/api/pwa/guru/disiplin/{$pelanggaran->id}/verifikasi", []);

        // Cek eskalasi dibuat
        $this->assertTrue(
            EskalasiDisiplin::query()
                ->where('santri_id', $this->santri->id)
                ->where('label', 'SP1')
                ->exists()
        );
    }
}
