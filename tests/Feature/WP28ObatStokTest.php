<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Kesehatan\Models\Obat;
use Modules\Kesehatan\Models\ObatMutasi;
use Modules\Kesehatan\Services\ObatService;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-28 — Stok Obat UKS.
 *
 * (a) masuk/keluar mencatat mutasi + update stok.
 * (b) Stok TAK BISA minus: keluar melebihi stok DITOLAK, stok tak berubah
 *     (service lockForUpdate + CHECK constraint stok >= 0 di DB).
 * (c) Keluar otomatis via register UKS (obat_keluar di catat-kunjungan)
 *     tercatat dengan ref KunjunganUks; UKS lama (field obat string) tetap jalan.
 * (d) Peringatan menipis: scope menipis + isMenipis pada ambang.
 */
class WP28ObatStokTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private ObatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ObatService;
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'obat', 'obat_mutasi', 'kunjungan_uks']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $tenant;
    }

    private function buatObat(int $stok = 20, int $ambang = 10): Obat
    {
        return Obat::create([
            'tenant_id' => tenant('id'),
            'nama' => 'Paracetamol '.str()->random(5),
            'satuan' => 'tablet',
            'stok' => $stok,
            'ambang_menipis' => $ambang,
            'aktif' => true,
        ]);
    }

    private function buatGuru(): User
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => 'Guru UKS',
            'email' => 'uks'.str()->random(8).'@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $u->givePermissionTo('akses_pwa_guru');

        return $u;
    }

    // ── (a) masuk / keluar dasar ────────────────────────────────────────────

    public function test_stok_masuk_dan_keluar_tercatat_mutasi(): void
    {
        $this->boot('ob1');
        $obat = $this->buatObat(stok: 0);

        $this->service->masuk($obat, 50, ['keterangan' => 'Belanja awal']);
        $this->assertSame(50, $obat->fresh()->stok);

        $this->service->keluar($obat->fresh(), 8);
        $this->assertSame(42, $obat->fresh()->stok);

        $this->assertSame(1, ObatMutasi::where('obat_id', $obat->id)->where('jenis', 'masuk')->count());
        $this->assertSame(1, ObatMutasi::where('obat_id', $obat->id)->where('jenis', 'keluar')->count());
    }

    public function test_jumlah_nol_atau_negatif_ditolak(): void
    {
        $this->boot('ob2');
        $obat = $this->buatObat();

        $this->expectException(InvalidArgumentException::class);
        $this->service->keluar($obat, 0);
    }

    // ── (b) stok tak bisa minus ─────────────────────────────────────────────

    public function test_keluar_melebihi_stok_ditolak_dan_stok_tak_berubah(): void
    {
        $this->boot('ob3');
        $obat = $this->buatObat(stok: 5);

        try {
            $this->service->keluar($obat, 6);
            $this->fail('Keluar melebihi stok HARUS ditolak');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak cukup', $e->getMessage());
        }

        // Stok tak berubah, tak ada mutasi keluar tercatat (transaksi rollback).
        $this->assertSame(5, $obat->fresh()->stok);
        $this->assertSame(0, ObatMutasi::where('obat_id', $obat->id)->where('jenis', 'keluar')->count());
    }

    public function test_pengeluaran_beruntun_berhenti_tepat_di_nol(): void
    {
        $this->boot('ob4');
        $obat = $this->buatObat(stok: 10);

        $this->service->keluar($obat, 7);
        $this->service->keluar($obat->fresh(), 3); // pas habis — boleh
        $this->assertSame(0, $obat->fresh()->stok);

        $this->expectException(InvalidArgumentException::class);
        $this->service->keluar($obat->fresh(), 1); // sudah 0 — tolak
    }

    // ── (c) keluar via register UKS (ref KunjunganUks) ─────────────────────

    public function test_obat_keluar_via_catat_kunjungan_tercatat_ref_kunjungan(): void
    {
        $this->boot('ob5');
        $guru = $this->buatGuru();
        $obat = $this->buatObat(stok: 20);
        $santri = Santri::create([
            'tenant_id' => tenant('id'), 'nis' => '2026001', 'nama' => 'Ahmad',
            'jenis_kelamin' => 'L', 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01',
        ]);

        $res = $this->actingAs($guru)->postJson('/api/pwa/guru/uks/catat-kunjungan', [
            'santri_id' => $santri->id,
            'keluhan' => 'Demam',
            'status' => 'pulang',
            'obat' => 'Paracetamol (catatan bebas lama tetap jalan)',
            'obat_keluar' => [['obat_id' => $obat->id, 'jumlah' => 2]],
        ]);

        $res->assertStatus(201);
        $this->assertSame(18, $obat->fresh()->stok);

        $kunjungan = KunjunganUks::where('santri_id', $santri->id)->firstOrFail();
        $mutasi = ObatMutasi::where('obat_id', $obat->id)->where('jenis', 'keluar')->firstOrFail();
        $this->assertSame($kunjungan->getMorphClass(), $mutasi->ref_type);
        $this->assertSame($kunjungan->id, (int) $mutasi->ref_id);
        $this->assertSame($guru->id, $mutasi->user_id);
    }

    public function test_catat_kunjungan_tanpa_obat_keluar_tetap_jalan(): void
    {
        $this->boot('ob6');
        $guru = $this->buatGuru();
        $santri = Santri::create([
            'tenant_id' => tenant('id'), 'nis' => '2026002', 'nama' => 'Budi',
            'jenis_kelamin' => 'L', 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01',
        ]);

        $res = $this->actingAs($guru)->postJson('/api/pwa/guru/uks/catat-kunjungan', [
            'santri_id' => $santri->id,
            'keluhan' => 'Batuk',
            'obat' => 'OBH sirup',
            'status' => 'pulang',
        ]);

        $res->assertStatus(201);
        $this->assertSame(0, ObatMutasi::count());
    }

    public function test_obat_keluar_stok_kurang_atomik_tidak_ada_yang_terpotong(): void
    {
        $this->boot('ob7');
        $guru = $this->buatGuru();
        $cukup = $this->buatObat(stok: 10);
        $kurang = $this->buatObat(stok: 1);
        $santri = Santri::create([
            'tenant_id' => tenant('id'), 'nis' => '2026003', 'nama' => 'Citra',
            'jenis_kelamin' => 'P', 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01',
        ]);

        $res = $this->actingAs($guru)->postJson('/api/pwa/guru/uks/catat-kunjungan', [
            'santri_id' => $santri->id,
            'status' => 'pulang',
            'obat_keluar' => [
                ['obat_id' => $cukup->id, 'jumlah' => 2],
                ['obat_id' => $kurang->id, 'jumlah' => 5], // stok kurang → gagal
            ],
        ]);

        $res->assertStatus(422);
        // All-or-nothing: item pertama TIDAK ikut terpotong.
        $this->assertSame(10, $cukup->fresh()->stok);
        $this->assertSame(1, $kurang->fresh()->stok);
        $this->assertSame(0, ObatMutasi::where('jenis', 'keluar')->count());
    }

    // ── (d) peringatan menipis ──────────────────────────────────────────────

    public function test_peringatan_stok_menipis_pada_dan_di_bawah_ambang(): void
    {
        $this->boot('ob8');
        $aman = $this->buatObat(stok: 11, ambang: 10);
        $pas = $this->buatObat(stok: 10, ambang: 10);
        $bawah = $this->buatObat(stok: 3, ambang: 10);

        $this->assertFalse($aman->isMenipis());
        $this->assertTrue($pas->isMenipis());
        $this->assertTrue($bawah->isMenipis());

        $menipisIds = Obat::menipis()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$pas->id, $bawah->id], $menipisIds);
    }

    public function test_api_daftar_obat_menyertakan_flag_menipis(): void
    {
        $this->boot('ob9');
        $guru = $this->buatGuru();
        $this->buatObat(stok: 2, ambang: 10);

        $res = $this->actingAs($guru)->getJson('/api/pwa/guru/uks/obat');
        $res->assertStatus(200);
        $this->assertTrue($res->json('0.menipis'));
    }
}
