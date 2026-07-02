<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\KegiatanTemplate;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kegiatan\Services\KegiatanLanjutService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G3-19 — Template clone + milestone/Gantt + recurring IDEMPOTEN + analitik.
 */
class KegiatanLanjutTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri',
            'kegiatan', 'presensi_kegiatan', 'kegiatan_template', 'kegiatan_phase',
            'kegiatan_recurring', 'audit_log',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function svc(): KegiatanLanjutService
    {
        return app(KegiatanLanjutService::class);
    }

    public function test_clone_template_membuat_kegiatan_dan_phase_default(): void
    {
        $this->boot('keg1');
        $tpl = $this->svc()->buatTemplate([
            'nama' => 'Pekan Olahraga', 'jenis' => 'umum',
            'default_phases' => [
                ['nama' => 'Persiapan', 'offset_hari' => 0, 'durasi_hari' => 2],
                ['nama' => 'Pelaksanaan', 'offset_hari' => 2, 'durasi_hari' => 1],
            ],
        ]);

        $keg = $this->svc()->cloneTemplate($tpl, ['mulai' => '2026-03-01']);
        $this->assertSame('Pekan Olahraga', $keg->nama);
        $this->assertSame($tpl->id, $keg->template_id);
        $phases = $keg->phases()->get();
        $this->assertCount(2, $phases);
        $this->assertSame('Persiapan', $phases[0]->nama);
        $this->assertSame('2026-03-01', $phases[0]->tanggal_mulai->toDateString());
        $this->assertSame('2026-03-02', $phases[0]->tanggal_selesai->toDateString()); // durasi 2 → +1
        $this->assertSame('2026-03-03', $phases[1]->tanggal_mulai->toDateString());     // offset 2
        tenancy()->end();
    }

    public function test_tambah_phase_tanggal_terbalik_ditolak(): void
    {
        $this->boot('keg2');
        $keg = $this->svc()->cloneTemplate($this->svc()->buatTemplate(['nama' => 'X']), ['mulai' => '2026-03-01']);
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->tambahPhase($keg, ['nama' => 'Salah', 'tanggal_mulai' => '2026-03-10', 'tanggal_selesai' => '2026-03-05']);
        tenancy()->end();
    }

    public function test_occurrences_mingguan_dan_bulanan(): void
    {
        $this->boot('keg3');
        $mingguan = $this->svc()->buatRecurring(['nama' => 'Rutin', 'frekuensi' => 'mingguan', 'interval' => 1, 'mulai' => '2026-01-05', 'akhir' => '2026-01-26']);
        $this->assertCount(4, $this->svc()->occurrences($mingguan, '2026-12-31')); // 5,12,19,26

        $dua = $this->svc()->buatRecurring(['nama' => 'Dwi', 'frekuensi' => 'mingguan', 'interval' => 2, 'mulai' => '2026-01-05', 'akhir' => '2026-02-02']);
        $this->assertCount(3, $this->svc()->occurrences($dua, '2026-12-31')); // 5,19,2-Feb

        $bulanan = $this->svc()->buatRecurring(['nama' => 'Bulanan', 'frekuensi' => 'bulanan', 'interval' => 1, 'mulai' => '2026-01-15', 'akhir' => '2026-04-15']);
        $this->assertCount(4, $this->svc()->occurrences($bulanan, '2026-12-31')); // Jan..Apr 15
        tenancy()->end();
    }

    public function test_generate_hingga_idempoten(): void
    {
        $this->boot('keg4');
        // Open-ended (akhir null) → window dibatasi parameter $sampai, bukan akhir.
        $rec = $this->svc()->buatRecurring(['nama' => 'Mingguan', 'frekuensi' => 'mingguan', 'interval' => 1, 'mulai' => '2026-01-05']);

        $n1 = $this->svc()->generateHingga($rec, '2026-01-26');
        $this->assertSame(4, $n1); // 5,12,19,26
        $this->assertSame(4, Kegiatan::where('recurring_id', $rec->id)->count());

        // Jalankan ULANG dgn window sama → tidak menggandakan (idempoten).
        $n2 = $this->svc()->generateHingga($rec, '2026-01-26');
        $this->assertSame(0, $n2);
        $this->assertSame(4, Kegiatan::where('recurring_id', $rec->id)->count());

        // Perpanjang window → hanya tambahan baru yang dibuat (2,9 Feb).
        $n3 = $this->svc()->generateHingga($rec->fresh(), '2026-02-09');
        $this->assertSame(2, $n3);
        $this->assertSame(6, Kegiatan::where('recurring_id', $rec->id)->count());
        tenancy()->end();
    }

    public function test_generate_dibatasi_akhir_recurring(): void
    {
        $this->boot('keg4b');
        // akhir 2026-01-26 → walau $sampai jauh, occurrences berhenti di akhir.
        $rec = $this->svc()->buatRecurring(['nama' => 'Terbatas', 'frekuensi' => 'mingguan', 'interval' => 1, 'mulai' => '2026-01-05', 'akhir' => '2026-01-26']);
        $this->assertSame(4, $this->svc()->generateHingga($rec, '2026-12-31'));
        $this->assertSame(0, $this->svc()->generateHingga($rec, '2026-12-31')); // tetap idempoten
        tenancy()->end();
    }

    public function test_generate_dari_template_membawa_phase(): void
    {
        $this->boot('keg5');
        $tpl = $this->svc()->buatTemplate(['nama' => 'Mabit', 'default_phases' => [['nama' => 'Sesi', 'offset_hari' => 0, 'durasi_hari' => 1]]]);
        $rec = $this->svc()->buatRecurring(['nama' => 'Mabit Rutin', 'template_id' => $tpl->id, 'frekuensi' => 'mingguan', 'interval' => 1, 'mulai' => '2026-01-05', 'akhir' => '2026-01-12']);
        $this->svc()->generateHingga($rec, '2026-01-12');

        $kegList = Kegiatan::where('recurring_id', $rec->id)->get();
        $this->assertCount(2, $kegList);
        $this->assertSame('Mabit', $kegList[0]->nama);
        $this->assertCount(1, $kegList[0]->phases()->get()); // phase dari template
        tenancy()->end();
    }

    public function test_rekap_partisipasi(): void
    {
        $this->boot('keg6');
        $keg = $this->svc()->cloneTemplate($this->svc()->buatTemplate(['nama' => 'Kajian']), ['mulai' => '2026-03-01']);
        foreach (['A', 'B', 'C'] as $x) {
            PresensiKegiatan::create(['kegiatan_id' => $keg->id, 'santri_id' => $this->santri($x)->id, 'status' => 'hadir']);
        }
        PresensiKegiatan::create(['kegiatan_id' => $keg->id, 'santri_id' => $this->santri('D')->id, 'status' => 'alpha']);

        $rekap = $this->svc()->rekapPartisipasi($keg);
        $this->assertSame(3, $rekap['hadir']);
        $this->assertSame(4, $rekap['total']);
        $this->assertEqualsWithDelta(75, $rekap['persen'], 0.01);
        tenancy()->end();
    }

    public function test_occurrences_window_ekstrem_ditolak(): void
    {
        $this->boot('keg7');
        // Open-ended + window sangat jauh → melebihi MAX_OCCURRENCE → tolak (bukan
        // generate diam-diam tak lengkap).
        $rec = $this->svc()->buatRecurring(['nama' => 'Selamanya', 'frekuensi' => 'mingguan', 'interval' => 1, 'mulai' => '2026-01-05']);
        $this->expectException(\RuntimeException::class);
        $this->svc()->occurrences($rec, '2060-01-01'); // ~34 tahun mingguan >> 730
        tenancy()->end();
    }

    public function test_isolasi_tenant_kegiatan_lanjut(): void
    {
        $this->boot('kegA');
        $this->svc()->buatTemplate(['nama' => 'T']);
        $this->assertSame(1, KegiatanTemplate::query()->count());
        tenancy()->end();

        $this->boot('kegB');
        $this->assertSame(0, KegiatanTemplate::query()->count(), 'Template tenant lain tak boleh terlihat.');
        tenancy()->end();
    }

    private function santri(string $nis): Santri
    {
        return Santri::create([
            'nis' => 'KEG'.$nis.substr(md5(uniqid()), 0, 4), 'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }
}
