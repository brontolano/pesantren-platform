<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\EnrollmentService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Stasiun: status langkah, foto/ttd/NFC/sampel via EnrollmentService. */
class EnrollmentServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'santri_enrollments', 'handwriting_samples']);
        if (! Schema::connection('pgsql')->hasTable('santri_enrollments')) {
            Artisan::call('migrate', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): Santri
    {
        $tenant = $this->createRuntimeTenant($prefix, 'P '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
        );

        return Santri::create(['nama' => 'Santri Enroll', 'nis' => 'EN-'.$prefix, 'status' => 'aktif',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
    }

    public function test_alur_lengkap_foto_ttd_nfc_sampel_dan_status(): void
    {
        Storage::fake('public');
        $santri = $this->boot('en1');
        $svc = app(EnrollmentService::class);

        // Status awal semua pending.
        $this->assertSame('pending', $svc->status($santri)['foto']);

        // Foto (base64 JPEG 1x1).
        $jpeg = 'data:image/jpeg;base64,'.base64_encode(hex2bin('ffd8ffe000104a46494600010100000100010000ffd9'));
        $fotoPath = $svc->simpanFoto($santri, $jpeg);
        Storage::disk('public')->assertExists($fotoPath);
        $this->assertSame($fotoPath, $santri->fresh()->photo_url);

        // TTD (SVG + PNG).
        $png = 'data:image/png;base64,'.base64_encode(hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'));
        $svgPath = $svc->simpanTtd($santri, '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>', $png);
        Storage::disk('public')->assertExists($svgPath);
        $this->assertNotNull($santri->fresh()->signature_svg_path);

        // NFC: normalisasi + tolak dipakai santri lain.
        $uid = $svc->tautkanNfc($santri, '04:A3-2B 7F 11 9C');
        $this->assertSame('04a32b7f119c', $uid);
        $lain = Santri::create(['nama' => 'Lain', 'nis' => 'EN-lain', 'status' => 'aktif',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'P']);
        try {
            $svc->tautkanNfc($lain, '04A32B7F119C');
            $this->fail('Kartu dobel harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        // Sampel tulisan + ground truth.
        $svc->simpanSampelTulisan($santri, 'latin', $jpeg, ['baris satu', 'baris dua']);
        $sample = $santri->handwritingSamples()->first();
        $this->assertSame(['baris satu', 'baris dua'], $sample->ground_truth);
        Storage::disk('public')->assertExists($sample->file_path);

        // Status terkumpul.
        $st = $svc->status($santri->fresh());
        $this->assertSame('done', $st['foto']);
        $this->assertSame('done', $st['ttd']);
        $this->assertSame('done', $st['nfc']);
        $this->assertSame('done', $st['tulisan_latin']);
        $this->assertSame('pending', $st['tulisan_arab']);

        // Skip + step tak dikenal ditolak.
        $svc->catat($santri, 'fingerprint', 'skipped');
        $this->assertSame('skipped', $svc->status($santri->fresh())['fingerprint']);
        $this->expectException(InvalidArgumentException::class);
        $svc->catat($santri, 'langkah_aneh');
        tenancy()->end();
    }
}
