<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\HasilUjian;
use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Services\UjianOnlineService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Ujian online: timer server, subset acak, auto-grade PG berbobot, essay manual. */
class UjianOnlineTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'calon_santri', 'paket_soal', 'soal', 'ujian_attempt', 'hasil_ujian',
        ]);
        $this->truncatePpdbTables(['ujian_attempt', 'hasil_ujian', 'soal', 'paket_soal', 'calon_santri', 'domains', 'tenants']);
        Carbon::setTestNow(); // reset
    }

    /** @return array{0:CalonSantri,1:PaketSoal} */
    private function boot(string $prefix, array $soal): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        $calon = CalonSantri::create([
            'nomor_pendaftaran' => 'UJ-'.strtoupper($prefix), 'nama' => 'Peserta Uji',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status_pendaftaran' => 'baru',
        ]);
        $paket = PaketSoal::create(['nama' => 'Paket '.$prefix, 'kategori_ujian' => 'Tulis', 'durasi_menit' => 30]);
        $paket->soal()->createMany($soal);

        return [$calon, $paket];
    }

    public function test_alur_pg_penuh_auto_grade_berbobot_dan_hasil_tercatat(): void
    {
        [$calon, $paket] = $this->boot('uj1', [
            ['tipe' => 'pg', 'pertanyaan' => 'A?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 2, 'sort' => 1],
            ['tipe' => 'pg', 'pertanyaan' => 'B?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'B', 'bobot' => 3, 'sort' => 2],
        ]);
        $svc = app(UjianOnlineService::class);

        $attempt = $svc->mulai($calon, $paket);
        $this->assertCount(2, $attempt->soal_tampil);
        $this->assertTrue($attempt->deadline_at->greaterThan(now()));

        // Resume (refresh) = attempt sama, timer TIDAK reset.
        $this->assertSame($attempt->id, $svc->mulai($calon, $paket)->id);

        [$id1, $id2] = $attempt->soal_tampil;
        $svc->jawab($attempt, $id1, 'a');   // benar (case-insensitive), bobot 2
        $svc->jawab($attempt, $id2, 'A');   // salah, bobot 3

        $selesai = $svc->selesaikan($attempt->fresh());
        $this->assertSame('selesai', $selesai->status);
        $this->assertEqualsWithDelta(40.0, (float) $selesai->nilai_pg, 0.01, '2/(2+3)×100 = 40.');
        $this->assertEqualsWithDelta(40.0, (float) $selesai->nilai_final, 0.01);
        // HasilUjian (dasar keputusan) tercatat otomatis.
        $this->assertEqualsWithDelta(40.0, (float) HasilUjian::where('calon_santri_id', $calon->id)->value('nilai'), 0.01);

        // Idempoten: selesaikan dua kali tak menggandakan/mengubah.
        $svc->selesaikan($selesai);
        $this->assertSame(1, HasilUjian::where('calon_santri_id', $calon->id)->count());
        tenancy()->end();
    }

    public function test_jawaban_setelah_deadline_ditolak_dan_tak_bisa_diulang(): void
    {
        [$calon, $paket] = $this->boot('uj2', [
            ['tipe' => 'pg', 'pertanyaan' => 'X?', 'pilihan' => ['A' => '1'], 'kunci' => 'A', 'bobot' => 1, 'sort' => 1],
        ]);
        $svc = app(UjianOnlineService::class);
        $attempt = $svc->mulai($calon, $paket);
        $id = $attempt->soal_tampil[0];

        Carbon::setTestNow(now()->addMinutes(31)); // lewat durasi 30 mnt
        try {
            $svc->jawab($attempt->fresh(), $id, 'A');
            $this->fail('Jawaban lewat deadline harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $svc->selesaikan($attempt->fresh()); // auto-submit: jawaban kosong → nilai 0
        $this->assertEqualsWithDelta(0.0, (float) $attempt->fresh()->nilai_final, 0.01);

        Carbon::setTestNow();
        $this->expectException(InvalidArgumentException::class); // tak bisa diulang
        $svc->mulai($calon, $paket);
        tenancy()->end();
    }

    public function test_essay_menunggu_nilai_lalu_final_tertimbang(): void
    {
        [$calon, $paket] = $this->boot('uj3', [
            ['tipe' => 'pg', 'pertanyaan' => 'P?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 2, 'sort' => 1],
            ['tipe' => 'essay', 'pertanyaan' => 'Uraikan.', 'bobot' => 8, 'sort' => 2],
        ]);
        $svc = app(UjianOnlineService::class);
        $attempt = $svc->mulai($calon, $paket);
        [$pgId, $essayId] = $attempt->soal_tampil;
        $svc->jawab($attempt, $pgId, 'A');           // benar (bobot 2)
        $svc->jawab($attempt->fresh(), $essayId, 'Jawaban panjang...');

        $selesai = $svc->selesaikan($attempt->fresh());
        $this->assertSame('menunggu_nilai', $selesai->status);
        $this->assertNull($selesai->nilai_final);
        $this->assertSame(0, HasilUjian::count(), 'Hasil BELUM ditulis sebelum essay dinilai.');

        // Skor essay melebihi bobot → ditolak.
        try {
            $svc->nilaiEssay($selesai, [$essayId => 9]);
            $this->fail('Skor > bobot harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $final = $svc->nilaiEssay($selesai->fresh(), [$essayId => 6]);
        // (2 + 6) / (2 + 8) × 100 = 80
        $this->assertEqualsWithDelta(80.0, (float) $final->nilai_final, 0.01);
        $this->assertEqualsWithDelta(75.0, (float) $final->nilai_essay, 0.01, '6/8×100.');
        $this->assertSame('selesai', $final->status);
        $this->assertEqualsWithDelta(80.0, (float) HasilUjian::where('calon_santri_id', $calon->id)->value('nilai'), 0.01);
        tenancy()->end();
    }

    public function test_subset_acak_jumlah_tampil(): void
    {
        [$calon, $paket] = $this->boot('uj4', array_map(
            fn (int $i) => ['tipe' => 'pg', 'pertanyaan' => "Q{$i}", 'pilihan' => ['A' => '1'], 'kunci' => 'A', 'bobot' => 1, 'sort' => $i],
            range(1, 10),
        ));
        $paket->update(['jumlah_tampil' => 4]);

        $attempt = app(UjianOnlineService::class)->mulai($calon, $paket->fresh());
        $this->assertCount(4, $attempt->soal_tampil, 'Subset acak sesuai jumlah_tampil.');
        $this->assertCount(4, array_unique($attempt->soal_tampil), 'Tanpa duplikat.');
        tenancy()->end();
    }
}
