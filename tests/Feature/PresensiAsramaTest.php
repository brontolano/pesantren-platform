<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\PresensiAsramaService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PresensiAsramaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'settings',
            'blok_asrama', 'kamar_asrama', 'penempatan_asrama',
            'halaqah', 'halaqah_santri', 'presensi_asrama',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function svc(): PresensiAsramaService
    {
        return app(PresensiAsramaService::class);
    }

    private function santri(string $nis = 'S1'): Santri
    {
        return Santri::create([
            'nis' => $nis, 'nama' => 'Santri '.$nis, 'tanggal_lahir' => '2011-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif', 'no_hp_wali' => '081234567890',
        ]);
    }

    private function user(string $email): User
    {
        return User::withoutGlobalScopes()->create([
            'tenant_id' => tenant('id'), 'name' => 'User '.$email,
            'email' => $email, 'password' => 'password123',
        ]);
    }

    private function kamar(string $nomor = 'K01'): KamarAsrama
    {
        $blok = BlokAsrama::create([
            'nama_blok' => 'Blok '.$nomor, 'is_active' => true,
        ]);

        return KamarAsrama::create([
            'blok_id' => $blok->id, 'nomor_kamar' => $nomor,
            'kapasitas' => 10, 'is_active' => true,
        ]);
    }

    private function tempatkan(Santri $santri, KamarAsrama $kamar): PenempatanAsrama
    {
        return PenempatanAsrama::create([
            'santri_id' => $santri->id, 'kamar_id' => $kamar->id,
            'tanggal_masuk' => now()->toDateString(), 'is_current' => true,
        ]);
    }

    // ---------------------------------------------------------------
    // (a) Slot nonaktif ditolak service
    // ---------------------------------------------------------------

    public function test_slot_nonaktif_ditolak(): void
    {
        $this->bootTenant('pa_a');
        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();
        $kamar = $this->kamar();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tidak aktif');

        $this->svc()->simpanPresensi(
            kamar: $kamar, halaqah: null,
            tanggal: Carbon::today(), slot: 'slot_bohong',
            dataSantri: [['santri_id' => $santri->id, 'status' => 'hadir']],
            pencatat: $pencatat,
        );
    }

    // ---------------------------------------------------------------
    // (b) Santri pindah kamar → tampil di kamar baru
    // ---------------------------------------------------------------

    public function test_santri_pindah_kamar_snapshot_kamar_baru(): void
    {
        $this->bootTenant('pa_b');
        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();
        $kamarLama = $this->kamar('K01');
        $kamarBaru = $this->kamar('K02');

        $this->tempatkan($santri, $kamarLama);

        $this->svc()->simpanPresensi(
            kamar: $kamarLama, halaqah: null,
            tanggal: Carbon::today(), slot: 'subuh',
            dataSantri: [['santri_id' => $santri->id, 'status' => 'hadir']],
            pencatat: $pencatat,
        );

        PenempatanAsrama::query()->where('santri_id', $santri->id)->update(['is_current' => false]);
        $this->tempatkan($santri, $kamarBaru);

        $this->svc()->simpanPresensi(
            kamar: $kamarBaru, halaqah: null,
            tanggal: Carbon::today(), slot: 'maghrib',
            dataSantri: [['santri_id' => $santri->id, 'status' => 'hadir']],
            pencatat: $pencatat,
        );

        $records = PresensiAsrama::query()->where('santri_id', $santri->id)->orderBy('slot')->get();
        $this->assertCount(2, $records);
        $this->assertSame($kamarBaru->id, $records->where('slot', 'maghrib')->first()->kamar_id);
        $this->assertSame($kamarLama->id, $records->where('slot', 'subuh')->first()->kamar_id);
    }

    // ---------------------------------------------------------------
    // (c) Bulk upsert idempoten
    // ---------------------------------------------------------------

    public function test_bulk_upsert_idempoten(): void
    {
        $this->bootTenant('pa_c');
        $pencatat = $this->user('musyrif@t.test');
        $s1 = $this->santri('S1');
        $s2 = $this->santri('S2');
        $kamar = $this->kamar();

        $data = [
            ['santri_id' => $s1->id, 'status' => 'hadir'],
            ['santri_id' => $s2->id, 'status' => 'alpha'],
        ];

        $this->svc()->simpanPresensi($kamar, null, Carbon::today(), 'subuh', $data, $pencatat);
        $this->svc()->simpanPresensi($kamar, null, Carbon::today(), 'subuh', $data, $pencatat);

        $this->assertSame(2, PresensiAsrama::query()->count(), 'Double simpan = same count (idempoten)');
    }

    // ---------------------------------------------------------------
    // (d) Policy: 2 tenant beda slot → perilaku beda
    // ---------------------------------------------------------------

    public function test_dua_tenant_beda_slot_presensi(): void
    {
        $t1 = $this->bootTenant('pa_d1');
        Setting::create([
            'key' => PresensiAsramaService::SETTING_SLOT_PRESENSI,
            'value' => [
                ['kode' => 'piket', 'label' => 'Piket Malam', 'jam_buka' => '22:00', 'jam_tutup' => '23:00'],
            ],
        ]);
        $slotsT1 = $this->svc()->kodeSlotAktif();
        $this->assertSame(['piket'], $slotsT1);

        tenancy()->end();

        $t2 = $this->bootTenant('pa_d2');
        $slotsT2 = $this->svc()->kodeSlotAktif();
        $this->assertSame(['subuh', 'maghrib', 'isya'], $slotsT2, 'Tenant 2 pakai default');

        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();

        $this->expectException(InvalidArgumentException::class);
        $this->svc()->simpanPresensi(
            null, null, Carbon::today(), 'piket',
            [['santri_id' => $santri->id, 'status' => 'hadir']],
            $pencatat,
        );
    }

    // ---------------------------------------------------------------
    // (e) Window tanggal: > hari ini ditolak, < 7 hari lalu ditolak
    // ---------------------------------------------------------------

    public function test_tanggal_masa_depan_ditolak(): void
    {
        $this->bootTenant('pa_e1');
        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lebih dari hari ini');

        $this->svc()->simpanPresensi(
            null, null, Carbon::tomorrow(), 'subuh',
            [['santri_id' => $santri->id, 'status' => 'hadir']],
            $pencatat,
        );
    }

    public function test_tanggal_lebih_dari_7_hari_lalu_ditolak(): void
    {
        $this->bootTenant('pa_e2');
        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('7 hari');

        $this->svc()->simpanPresensi(
            null, null, Carbon::today()->subDays(8), 'subuh',
            [['santri_id' => $santri->id, 'status' => 'hadir']],
            $pencatat,
        );
    }

    public function test_tanggal_dalam_window_diterima(): void
    {
        $this->bootTenant('pa_e3');
        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();

        $count = $this->svc()->simpanPresensi(
            null, null, Carbon::today()->subDays(6), 'subuh',
            [['santri_id' => $santri->id, 'status' => 'hadir']],
            $pencatat,
        );

        $this->assertSame(1, $count);
    }

    // ---------------------------------------------------------------
    // (f) Default hadir ON vs OFF
    // ---------------------------------------------------------------

    public function test_default_hadir_on(): void
    {
        $this->bootTenant('pa_f1');
        $this->assertTrue($this->svc()->defaultHadir(), 'Default hadir = true');
    }

    public function test_default_hadir_off(): void
    {
        $this->bootTenant('pa_f2');
        Setting::create([
            'key' => PresensiAsramaService::SETTING_DEFAULT_HADIR,
            'value' => false,
        ]);
        $this->assertFalse($this->svc()->defaultHadir());
    }

    // ---------------------------------------------------------------
    // (g) Rekap kamar & rekap santri
    // ---------------------------------------------------------------

    public function test_rekap_kamar(): void
    {
        $this->bootTenant('pa_g');
        $pencatat = $this->user('musyrif@t.test');
        $s1 = $this->santri('S1');
        $s2 = $this->santri('S2');
        $kamar = $this->kamar();

        $this->svc()->simpanPresensi($kamar, null, Carbon::today(), 'subuh', [
            ['santri_id' => $s1->id, 'status' => 'hadir'],
            ['santri_id' => $s2->id, 'status' => 'alpha'],
        ], $pencatat);

        $rekap = $this->svc()->rekapKamar($kamar->id, Carbon::today(), 'subuh');
        $this->assertSame(1, $rekap['subuh']['hadir']);
        $this->assertSame(1, $rekap['subuh']['alpha']);
    }

    public function test_rekap_santri(): void
    {
        $this->bootTenant('pa_h');
        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();

        $this->svc()->simpanPresensi(null, null, Carbon::today(), 'subuh', [
            ['santri_id' => $santri->id, 'status' => 'hadir'],
        ], $pencatat);
        $this->svc()->simpanPresensi(null, null, Carbon::today(), 'maghrib', [
            ['santri_id' => $santri->id, 'status' => 'izin', 'keterangan' => 'Sakit perut'],
        ], $pencatat);

        $rekap = $this->svc()->rekapSantri($santri->id, Carbon::today()->subDay(), Carbon::today());
        $this->assertCount(2, $rekap);
    }

    // ---------------------------------------------------------------
    // (h) Status CHECK constraint (pgsql)
    // ---------------------------------------------------------------

    public function test_status_liar_ditolak(): void
    {
        $this->bootTenant('pa_chk');
        $pencatat = $this->user('musyrif@t.test');
        $santri = $this->santri();

        $this->expectException(InvalidArgumentException::class);

        $this->svc()->simpanPresensi(
            null, null, Carbon::today(), 'subuh',
            [['santri_id' => $santri->id, 'status' => 'bolos']],
            $pencatat,
        );
    }
}
