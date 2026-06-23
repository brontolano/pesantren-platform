<?php

namespace Tests\Feature;

use Modules\Keuangan\Filament\Resources\Tagihan\Pages\CreateTagihan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Beasiswa;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\BeasiswaService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\SumberDana;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * 2c — Beasiswa potong SPP: diterapkan via kolom diskon existing SETELAH
 * generate jalur TagihanService (math existing nol diff).
 */
class BaitulMaalBeasiswaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('beasiswa')
            || ! Schema::connection('pgsql')->hasColumn('tagihan', 'diskon_beasiswa')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'beasiswa',
            'tagihan',
            'domains',
            'tenants',
        ]);
    }

    /** @return array{0: Kelas, 1: Santri, 2: Santri} */
    private function siapkanKelasDuaSantri(): array
    {
        $tenant = $this->createRuntimeTenant('bea', 'Pesantren Beasiswa');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();

        $kelas = Kelas::create([
            'nama_kelas' => 'Kelas 1A',
            'tingkat' => '1',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $penerima = Santri::create([
            'nis' => 'B-'.str()->random(5),
            'nama' => 'Santri Penerima',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        $biasa = Santri::create([
            'nis' => 'B-'.str()->random(5),
            'nama' => 'Santri Biasa',
            'tanggal_lahir' => '2012-02-02',
            'jenis_kelamin' => 'P',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        return [$kelas, $penerima, $biasa];
    }

    private function payloadSpp(float $nominal = 300000): array
    {
        return [
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'jatuh_tempo' => '2026-07-10',
            'bulan_masehi' => '2026-07',
        ];
    }

    public function test_penerima_dipotong_nominal_benar_dan_non_penerima_utuh(): void
    {
        [$kelas, $penerima] = $this->siapkanKelasDuaSantri();

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 100000,
            'aktif' => true,
            'mulai' => '2026-01-01',
        ]);

        $tagihanList = app(BeasiswaService::class)->buatTagihanSppPerKelas($kelas, $this->payloadSpp(300000));

        $this->assertCount(2, $tagihanList);

        $tagihanPenerima = Tagihan::where('santri_id', $penerima->id)->firstOrFail();
        $this->assertSame('0.00', (string) $tagihanPenerima->diskon, 'Diskon manual TU tidak boleh tersentuh.');
        $this->assertSame('100000.00', (string) $tagihanPenerima->diskon_beasiswa);
        $this->assertSame('200000.00', (string) $tagihanPenerima->nominal_akhir);
        $this->assertSame('300000.00', (string) $tagihanPenerima->nominal, 'Nominal bruto tidak boleh berubah.');

        $tagihanBiasa = Tagihan::where('santri_id', '!=', $penerima->id)->firstOrFail();
        $this->assertSame('0.00', (string) $tagihanBiasa->diskon_beasiswa);
        $this->assertSame('300000.00', (string) $tagihanBiasa->nominal_akhir);
    }

    public function test_beasiswa_nonaktif_atau_kedaluwarsa_tidak_memotong(): void
    {
        [$kelas, $penerima] = $this->siapkanKelasDuaSantri();

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 100000,
            'aktif' => false, // nonaktif
            'mulai' => '2026-01-01',
        ]);

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '04')->value('id'),
            'nominal_per_bulan' => 50000,
            'aktif' => true,
            'mulai' => '2025-01-01',
            'selesai' => '2025-12-31', // kedaluwarsa sebelum periode tagihan
        ]);

        app(BeasiswaService::class)->buatTagihanSppPerKelas($kelas, $this->payloadSpp(300000));

        $tagihan = Tagihan::where('santri_id', $penerima->id)->firstOrFail();
        $this->assertSame('0.00', (string) $tagihan->diskon_beasiswa);
        $this->assertSame('300000.00', (string) $tagihan->nominal_akhir);
    }

    public function test_potongan_tidak_membuat_nominal_akhir_negatif(): void
    {
        [$kelas, $penerima] = $this->siapkanKelasDuaSantri();

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 999999, // lebih besar dari nominal SPP
            'aktif' => true,
            'mulai' => '2026-01-01',
        ]);

        app(BeasiswaService::class)->buatTagihanSppPerKelas($kelas, $this->payloadSpp(300000));

        $tagihan = Tagihan::where('santri_id', $penerima->id)->firstOrFail();
        $this->assertSame('300000.00', (string) $tagihan->diskon_beasiswa, 'Potongan dibatasi nominal tagihan.');
        $this->assertSame('0.00', (string) $tagihan->nominal_akhir);
    }

    public function test_tagihan_non_spp_tidak_terpotong_beasiswa(): void
    {
        [$kelas, $penerima] = $this->siapkanKelasDuaSantri();

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 100000,
            'aktif' => true,
            'mulai' => '2026-01-01',
        ]);

        app(BeasiswaService::class)->buatTagihanSppPerKelas($kelas, [
            'jenis_tagihan' => 'seragam',
            'nominal' => 150000,
            'jatuh_tempo' => '2026-07-10',
            'bulan_masehi' => '2026-07',
        ]);

        $tagihan = Tagihan::where('santri_id', $penerima->id)->firstOrFail();
        $this->assertSame('0.00', (string) $tagihan->diskon_beasiswa);
        $this->assertSame('150000.00', (string) $tagihan->nominal_akhir);
    }

    public function test_terapkan_ulang_idempoten_tidak_menumpuk(): void
    {
        [$kelas, $penerima] = $this->siapkanKelasDuaSantri();

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 100000,
            'aktif' => true,
            'mulai' => '2026-01-01',
        ]);

        app(BeasiswaService::class)->buatTagihanSppPerKelas($kelas, $this->payloadSpp(300000));

        $tagihan = Tagihan::where('santri_id', $penerima->id)->firstOrFail();

        // Re-run 2x — diskon_beasiswa di-SET, bukan ditambahkan.
        app(BeasiswaService::class)->terapkanPada($tagihan->fresh());
        app(BeasiswaService::class)->terapkanPada($tagihan->fresh());

        $tagihan->refresh();
        $this->assertSame('100000.00', (string) $tagihan->diskon_beasiswa, 'Re-run tidak boleh menumpuk potongan.');
        $this->assertSame('0.00', (string) $tagihan->diskon);
        $this->assertSame('200000.00', (string) $tagihan->nominal_akhir);
    }

    public function test_tagihan_sudah_dibayar_tidak_disentuh(): void
    {
        [$kelas, $penerima] = $this->siapkanKelasDuaSantri();

        app(BeasiswaService::class)->buatTagihanSppPerKelas($kelas, $this->payloadSpp(300000));

        $tagihan = Tagihan::where('santri_id', $penerima->id)->firstOrFail();
        $tagihan->forceFill(['status' => 'lunas'])->save();

        // Beasiswa baru muncul SETELAH tagihan lunas → tidak boleh mengubah apapun.
        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 100000,
            'aktif' => true,
            'mulai' => '2026-01-01',
        ]);

        app(BeasiswaService::class)->terapkanPada($tagihan->fresh());

        $tagihan->refresh();
        $this->assertSame('0.00', (string) $tagihan->diskon_beasiswa, 'Tagihan lunas tidak boleh dipotong.');
        $this->assertSame('300000.00', (string) $tagihan->nominal_akhir);
    }

    public function test_santri_nonaktif_tidak_dipotong(): void
    {
        [$kelas, $penerima] = $this->siapkanKelasDuaSantri();

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 100000,
            'aktif' => true,
            'mulai' => '2026-01-01',
        ]);

        $penerima->forceFill(['status' => 'keluar'])->save();

        app(BeasiswaService::class)->buatTagihanSppPerKelas($kelas, $this->payloadSpp(300000));

        $tagihan = Tagihan::where('santri_id', $penerima->id)->first();
        if ($tagihan !== null) {
            $this->assertSame('0.00', (string) $tagihan->diskon_beasiswa, 'Santri nonaktif tidak berhak potongan.');
            $this->assertSame('300000.00', (string) $tagihan->nominal_akhir);
        } else {
            // Jalur generate existing memang melewati santri nonaktif — sama-sama aman.
            $this->assertNull($tagihan);
        }
    }

    public function test_create_tagihan_panel_menerapkan_beasiswa(): void
    {
        [, $penerima] = $this->siapkanKelasDuaSantri();

        Beasiswa::create([
            'santri_id' => $penerima->id,
            'sumber_dana_id' => SumberDana::where('kode_dana', '05')->value('id'),
            'nominal_per_bulan' => 100000,
            'aktif' => true,
            'mulai' => '2026-01-01',
        ]);

        // Modul keuangan harus aktif untuk tenant agar TagihanResource::canAccess lolos.
        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_modules')
            ->updateOrInsert(
                ['tenant_id' => tenant('id'), 'module_name' => 'keuangan'],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );

        Permission::findOrCreate('manage_keuangan');
        $user = User::create([
            'name' => 'TU Keuangan',
            'email' => 'tu-bea@test.local',
            'password' => 'password',
        ]);
        $user->givePermissionTo('manage_keuangan');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $component = Livewire::test(CreateTagihan::class);
        $component->assertOk();
        $component
            ->fillForm([
                'santri_id' => $penerima->id,
                'jenis_tagihan' => 'spp',
                'nominal' => 300000,
                'diskon' => 0,
                'jatuh_tempo' => '2026-07-10',
                'bulan_masehi' => '2026-07',
                'status' => 'belum_bayar',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tagihan = Tagihan::where('santri_id', $penerima->id)->firstOrFail();
        $this->assertSame('100000.00', (string) $tagihan->diskon_beasiswa, 'Jalur panel CreateTagihan wajib menerapkan beasiswa.');
        $this->assertSame('200000.00', (string) $tagihan->nominal_akhir);
    }
}
