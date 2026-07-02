<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;
use Modules\Payroll\Filament\Resources\Kontrak\KontrakKerjaResource;
use Modules\Payroll\Filament\Resources\Kontrak\Pages\CreateKontrakKerja;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\KontrakKerja;
use Modules\Payroll\Services\KontrakService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-16 — Kontrak, SK & Pengabdian.
 *
 * (a) Boundary ambang reminder (59/60/61 hari, default Setting 60).
 * (b) Policy-first: 2 tenant ambang beda → reminder beda.
 * (c) Perpanjang: baris baru bertaut parent_id, arsip lama UTUH
 *     (status diperpanjang + file lama tetap), kontrak aktif baru benar.
 * (d) Upload non-pdf/jpg/png ditolak (acceptedFileTypes — pelajaran WP-08).
 * (e) Command payroll:cek-kontrak-habis idempoten per hari
 *     (guard last_reminded_at, tidak spam dobel).
 */
class KontrakKerjaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'employee', 'kontrak_kerja',
            'settings', 'template_notifikasi', 'broadcast_wa',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function buatEmployee(string $nama): Employee
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => $nama, 'email' => 'u'.str()->random(6).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);

        return Employee::create(['users_id' => $u->id, 'nama' => $nama, 'ptkp_status' => 'TK0', 'aktif' => true]);
    }

    private function buatKontrak(Employee $e, array $override = []): KontrakKerja
    {
        return KontrakKerja::create(array_merge([
            'employee_id' => $e->id,
            'jenis' => 'kontrak',
            'nomor' => 'SK/'.str()->random(6),
            'mulai' => today()->subYear(),
            'selesai' => today()->addDays(30),
            'status' => 'aktif',
        ], $override));
    }

    // ---------------------------------------------------------------
    // (a) Boundary ambang — default 60 hari
    // ---------------------------------------------------------------

    public function test_reminder_boundary_59_60_61_hari(): void
    {
        $this->boot('ktr1');
        $e = $this->buatEmployee('Ust A');

        $k59 = $this->buatKontrak($e, ['selesai' => today()->addDays(59)]);
        $k60 = $this->buatKontrak($e, ['selesai' => today()->addDays(60)]);
        $k61 = $this->buatKontrak($e, ['selesai' => today()->addDays(61)]);

        $this->assertSame(60, KontrakKerja::reminderHari(), 'Default ambang harus 60');

        $ids = KontrakKerja::query()->mendekatiHabis()->pluck('id')->all();
        $this->assertContains($k59->id, $ids, '59 hari lagi → masuk ambang');
        $this->assertContains($k60->id, $ids, 'tepat 60 hari → masuk ambang (inklusif)');
        $this->assertNotContains($k61->id, $ids, '61 hari lagi → di luar ambang');
    }

    public function test_kontrak_non_aktif_atau_tanpa_selesai_tidak_masuk_reminder(): void
    {
        $this->boot('ktr2');
        $e = $this->buatEmployee('Ust B');

        $this->buatKontrak($e, ['selesai' => today()->addDays(5), 'status' => 'berakhir']);
        $this->buatKontrak($e, ['selesai' => null]); // SK GTY tanpa batas

        $this->assertSame(0, KontrakKerja::query()->mendekatiHabis()->count());
    }

    // ---------------------------------------------------------------
    // (b) Policy-first: 2 tenant ambang beda
    // ---------------------------------------------------------------

    public function test_dua_tenant_ambang_beda_reminder_beda(): void
    {
        // Tenant A: ambang ketat 30 hari → kontrak 45 hari lagi TIDAK diingatkan.
        $this->boot('ktrA');
        Setting::create(['key' => KontrakKerja::SETTING_REMINDER_HARI, 'value' => ['value' => 30]]);
        $eA = $this->buatEmployee('Ust A');
        $this->buatKontrak($eA, ['selesai' => today()->addDays(45)]);

        $this->assertSame(30, KontrakKerja::reminderHari());
        $this->assertSame(0, KontrakKerja::query()->mendekatiHabis()->count());
        tenancy()->end();

        // Tenant B: ambang longgar 90 hari → kontrak 45 hari lagi DIINGATKAN.
        $this->boot('ktrB');
        Setting::create(['key' => KontrakKerja::SETTING_REMINDER_HARI, 'value' => ['value' => 90]]);
        $eB = $this->buatEmployee('Ust B');
        $this->buatKontrak($eB, ['selesai' => today()->addDays(45)]);

        $this->assertSame(90, KontrakKerja::reminderHari());
        $this->assertSame(1, KontrakKerja::query()->mendekatiHabis()->count());
    }

    // ---------------------------------------------------------------
    // (c) Perpanjang — arsip lama utuh
    // ---------------------------------------------------------------

    public function test_perpanjang_membuat_baris_baru_dan_arsip_lama_utuh(): void
    {
        $this->boot('ktr3');
        $e = $this->buatEmployee('Ust C');
        $lama = $this->buatKontrak($e, [
            'nomor' => 'SK/2025/001',
            'file' => 'kontrak-kerja/arsip-lama.pdf',
            'selesai' => today()->addDays(10),
        ]);

        $baru = app(KontrakService::class)->perpanjang($lama, [
            'nomor' => 'SK/2026/002',
            'mulai' => today()->addDays(11)->toDateString(),
            'selesai' => today()->addYear()->toDateString(),
            'file' => 'kontrak-kerja/arsip-baru.pdf',
        ]);

        $lama->refresh();
        // Baris lama: hanya status berubah — arsip & identitas UTUH.
        $this->assertSame('diperpanjang', $lama->status);
        $this->assertSame('kontrak-kerja/arsip-lama.pdf', $lama->file, 'File lama TIDAK BOLEH tertimpa');
        $this->assertSame('SK/2025/001', $lama->nomor);

        // Baris baru: aktif, bertaut parent, jenis sama, file baru.
        $this->assertSame('aktif', $baru->status);
        $this->assertSame($lama->id, $baru->parent_id);
        $this->assertSame($lama->employee_id, $baru->employee_id);
        $this->assertSame($lama->jenis, $baru->jenis);
        $this->assertSame('kontrak-kerja/arsip-baru.pdf', $baru->file);

        // Tepat SATU kontrak aktif untuk rantai ini.
        $this->assertSame(1, KontrakKerja::query()->where('status', 'aktif')->count());
    }

    public function test_perpanjang_kontrak_non_aktif_ditolak(): void
    {
        $this->boot('ktr4');
        $e = $this->buatEmployee('Ust D');
        $lama = $this->buatKontrak($e, ['status' => 'berakhir']);

        $this->expectException(\RuntimeException::class);
        app(KontrakService::class)->perpanjang($lama, ['nomor' => 'X', 'mulai' => today()->toDateString()]);
    }

    // ---------------------------------------------------------------
    // (d) Upload non-pdf ditolak (WP-08)
    // ---------------------------------------------------------------

    public function test_form_upload_hanya_menerima_pdf_jpg_png(): void
    {
        $this->boot('ktr5');

        $livewire = app(CreateKontrakKerja::class);
        $schema = KontrakKerjaResource::form(Schema::make($livewire));
        $uploads = collect($schema->getFlatComponents(withHidden: true))
            ->filter(fn ($c) => $c instanceof BaseFileUpload);

        $this->assertGreaterThan(0, $uploads->count(), 'Form harus punya FileUpload arsip');
        foreach ($uploads as $upload) {
            $types = $upload->getAcceptedFileTypes();
            $this->assertSame(
                ['application/pdf', 'image/jpeg', 'image/png'],
                $types,
                'acceptedFileTypes WAJIB pdf/jpg/png saja — exe/svg/html DITOLAK (stored-XSS WP-08)',
            );
        }
    }

    // ---------------------------------------------------------------
    // (e) Command idempoten per hari
    // ---------------------------------------------------------------

    public function test_command_cek_kontrak_habis_idempoten_per_hari(): void
    {
        $tenant = $this->boot('ktr6');
        $e = $this->buatEmployee('Ust E');
        $k = $this->buatKontrak($e, ['selesai' => today()->addDays(10)]);
        tenancy()->end();

        // Run 1: kandidat di-stamp last_reminded_at hari ini.
        Artisan::call('payroll:cek-kontrak-habis', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        $k->refresh();
        $this->assertNotNull($k->last_reminded_at);
        $this->assertTrue($k->last_reminded_at->isToday(), 'Guard sudah-dikirim harus ter-stamp hari ini');
        $stampPertama = $k->updated_at;
        tenancy()->end();

        // Run 2 (hari yang sama): TIDAK ada kandidat → tidak spam dobel.
        Artisan::call('payroll:cek-kontrak-habis', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        $k->refresh();
        $this->assertTrue($k->last_reminded_at->isToday());
        $this->assertEquals($stampPertama->toDateTimeString(), $k->updated_at->toDateTimeString(),
            'Run kedua di hari sama tidak boleh menyentuh baris (idempoten)');

        // Simulasi hari berikutnya: stamp kemarin → kembali jadi kandidat.
        $k->forceFill(['last_reminded_at' => today()->subDay()])->save();
        tenancy()->end();

        Artisan::call('payroll:cek-kontrak-habis', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        $k->refresh();
        $this->assertTrue($k->last_reminded_at->isToday(), 'Hari berikutnya diingatkan lagi (sekali per hari)');
    }
}
