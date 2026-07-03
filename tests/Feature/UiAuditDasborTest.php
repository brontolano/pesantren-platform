<?php

namespace Tests\Feature;

use App\Filament\Pages\PengaturanBranding;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Widgets\KesantrianOverviewWidget;
use App\Filament\Widgets\KeuanganOverviewWidget;
use App\Filament\Widgets\QuickAccessWidget;
use App\Filament\Widgets\StatsPesantren;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\Filament\TenantPanelProvider;
use App\Support\TenantBranding;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Pengadaan\Models\Belanja;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Regresi audit tampilan panel (fix/ui-audit-dasbor):
 * - Kartu stats dasbor JUJUR: "Tagihan Lewat Jatuh Tempo" (satu definisi dgn
 *   KeuanganOverviewWidget) + deskripsi dinamis kas masuk/keluar.
 * - Konsolidasi dasbor: satu baris stats saja (Overview domain dicabut).
 * - QuickAccessWidget TIDAK terdaftar (dicabut lagi, owner 2026-07-03);
 *   favicon branding tenant; label Indo.
 */
class UiAuditDasborTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'settings', 'santri', 'tagihan', 'pembayaran', 'belanja',
        ]);
        $this->truncatePpdbTables([
            'domains', 'tenants', 'santri', 'tagihan', 'pembayaran', 'belanja', 'settings',
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function siapkanTenantDenganAdmin(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Admin Uji',
            'email' => $prefix.'@uji.test',
            'password' => 'password123',
        ]);
        $user->assignRole('super_admin');
        Auth::login($user);

        return $tenant;
    }

    private function buatSantri(string $nis, string $status = 'aktif'): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => $status,
        ]);
    }

    private function buatTagihan(Santri $s, float $nominal, string $status, string $jatuhTempo): Tagihan
    {
        return Tagihan::create([
            'santri_id' => $s->id,
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'nominal_akhir' => $nominal,
            'status' => $status,
            'jatuh_tempo' => $jatuhTempo,
        ]);
    }

    /** @return array<int, Stat> */
    private function statsWidget(): array
    {
        $widget = new StatsPesantren;

        return (fn (): array => $this->getStats())->call($widget);
    }

    private function cariStat(array $stats, string $label): Stat
    {
        foreach ($stats as $stat) {
            if ((string) $stat->getLabel() === $label) {
                return $stat;
            }
        }

        $this->fail("Stat berlabel '{$label}' tidak ditemukan di StatsPesantren.");
    }

    public function test_stat_tagihan_hanya_hitung_lewat_jatuh_tempo_dan_tampilkan_sisa(): void
    {
        $this->siapkanTenantDenganAdmin('uidsb1');
        $santri = $this->buatSantri('UID001');

        // Lewat jatuh tempo: 2 tagihan (100k belum bayar + 200k sebagian dgn
        // pembayaran terverifikasi 50k) → count = 2, sisa = 250k.
        $this->buatTagihan($santri, 100000, 'belum_bayar', now()->subDays(10)->toDateString());
        $sebagian = $this->buatTagihan($santri, 200000, 'sebagian', now()->subDays(5)->toDateString());
        Pembayaran::create([
            'tagihan_id' => $sebagian->id,
            'santri_id' => $santri->id,
            'nominal' => 50000,
            'metode' => 'tunai',
            'tanggal_pembayaran' => now()->subDays(4)->toDateString(),
            'is_verified' => true,
        ]);

        // TIDAK ikut: belum jatuh tempo, dan yang lunas.
        $this->buatTagihan($santri, 999000, 'belum_bayar', now()->addMonth()->toDateString());
        $this->buatTagihan($santri, 500000, 'lunas', now()->subDays(3)->toDateString());

        $stat = $this->cariStat($this->statsWidget(), 'Tagihan Lewat Jatuh Tempo');

        $this->assertSame('2', (string) $stat->getValue(), 'Hanya tagihan belum lunas yang LEWAT jatuh tempo yang dihitung.');
        $this->assertStringContainsString('250.000', (string) $stat->getDescription(), 'Deskripsi memuat total nominal sisa.');
    }

    public function test_stat_kas_masuk_nol_menjelaskan_pembayaran_menunggu_verifikasi(): void
    {
        $this->siapkanTenantDenganAdmin('uidsb2');
        $santri = $this->buatSantri('UID002');
        $tagihan = $this->buatTagihan($santri, 300000, 'belum_bayar', now()->addWeek()->toDateString());

        // Ada pembayaran bulan ini tapi BELUM terverifikasi → angka utama Rp 0,
        // deskripsi jujur menjelaskan nominal yang antre verifikasi.
        Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'santri_id' => $santri->id,
            'nominal' => 150000,
            'metode' => 'transfer',
            'tanggal_pembayaran' => now()->toDateString(),
            'is_verified' => false,
        ]);

        $stat = $this->cariStat($this->statsWidget(), 'Kas Masuk (Bulan Ini)');

        $this->assertSame('Rp 0', (string) $stat->getValue(), 'Angka utama tetap definisi ketat (terverifikasi).');
        $this->assertStringContainsString('150.000', (string) $stat->getDescription());
        $this->assertStringContainsString('menunggu verifikasi', (string) $stat->getDescription());
    }

    public function test_stat_kas_keluar_nol_menampilkan_belanja_belum_terposting(): void
    {
        $tenant = $this->siapkanTenantDenganAdmin('uidsb3');

        // Belanja bulan ini TANPA jurnal (tenant tak pakai posting) → angka
        // utama Rp 0, deskripsi menampilkan totalnya berlabel belum terposting.
        Belanja::create([
            'tenant_id' => $tenant->id,
            'tanggal' => now()->toDateString(),
            'uraian' => 'Beras dapur',
            'jenis' => 'barang',
            'dpp' => 750000,
            'total' => 750000,
        ]);

        $stat = $this->cariStat($this->statsWidget(), 'Kas Keluar (Bulan Ini)');

        $this->assertSame('Rp 0', (string) $stat->getValue(), 'Angka utama tetap definisi ketat (terposting).');
        $this->assertStringContainsString('750.000', (string) $stat->getDescription());
        $this->assertStringContainsString('belum terposting', (string) $stat->getDescription());
    }

    public function test_dasbor_tenant_hanya_satu_baris_stats_dan_quick_access_tidak_terdaftar(): void
    {
        $widgets = Filament::getPanel('tenant')->getWidgets();

        $this->assertContains(StatsPesantren::class, $widgets, 'StatsPesantren = satu-satunya baris stats dasbor.');
        $this->assertNotContains(QuickAccessWidget::class, $widgets, 'QuickAccessWidget DICABUT (keputusan owner 2026-07-03: pintasan tak diinginkan; super admin melihat semuanya).');
        $this->assertNotContains(KesantrianOverviewWidget::class, $widgets, 'Ringkasan Kesantrian dicabut dari dasbor utama (dobel definisi).');
        $this->assertNotContains(KeuanganOverviewWidget::class, $widgets, 'Ringkasan Keuangan dicabut dari dasbor utama (dobel definisi).');
    }

    public function test_favicon_tenant_dipakai_dan_fallback_default_platform(): void
    {
        $this->siapkanTenantDenganAdmin('uidsb4');

        // Tanpa setting → null (panel jatuh ke aset default platform).
        $this->assertNull(TenantBranding::faviconUrl());

        // Dengan file favicon tenant → di-serve via /_tenant-storage
        // (Storage::url 404 di Docker + stancl/tenancy).
        Setting::updateOrCreate(
            ['key' => 'branding_favicon'],
            ['value' => ['favicon_path' => 'branding/favicon.png']],
        );

        $url = TenantBranding::faviconUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('/_tenant-storage/branding/favicon.png', $url);
    }

    public function test_label_navigasi_berbahasa_indonesia(): void
    {
        $this->assertSame('Peran & Izin', RoleResource::getNavigationLabel());
        $this->assertSame('Pengaturan & Branding', PengaturanBranding::getNavigationLabel());
    }

    public function test_brand_name_fallback_nama_tenant_bukan_pesantren_platform(): void
    {
        $tenant = $this->siapkanTenantDenganAdmin('uidsb5');

        $nama = (new \ReflectionMethod(TenantPanelProvider::class, 'resolveTenantBrandName'))->invoke(null);

        $this->assertSame($tenant->name, $nama, 'brandName fallback = nama tenant, bukan "Pesantren Platform".');
    }
}
