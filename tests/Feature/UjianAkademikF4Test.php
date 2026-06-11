<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Akademik\Models\KomponenNilai;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\SesiUjianAkademik;
use Modules\Akademik\Services\UjianAkademikService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Models\UjianAttempt;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Ujian Semester Online di atas engine ujian PPDB (EXTRACT additif):
 * permission pengampu, sesi per rombel, satu attempt per sesi, koreksi
 * essay, tarik nilai → komponen (idempoten). Regresi PPDB dijaga oleh
 * UjianOnlineTest yang TIDAK diubah.
 */
class UjianAkademikF4Test extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'kelas', 'santri', 'pelajaran', 'periode_akademik',
            'komponen_nilai', 'nilai', 'paket_soal', 'soal', 'ujian_attempt', 'sesi_ujian_akademik',
        ]);
        $this->truncatePpdbTables([
            'nilai', 'komponen_nilai', 'ujian_attempt', 'sesi_ujian_akademik', 'soal', 'paket_soal',
            'santri', 'kelas', 'pelajaran', 'periode_akademik', 'users', 'domains', 'tenants',
        ]);
        Carbon::setTestNow();
    }

    /**
     * @return array{guru:User, pelajaran:Pelajaran, kelas:Kelas, santri:Santri, periode:PeriodeAkademik, paket:PaketSoal, sesi:SesiUjianAkademik, komponen:KomponenNilai}
     */
    private function boot(string $prefix, array $soal): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $guru = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Guru '.$prefix,
            'email' => $prefix.'-guru@test.local', 'password' => 'password123',
        ]);

        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'guru_pengampu_id' => $guru->id]);
        $kelas = Kelas::create(['nama_kelas' => '7A', 'tingkat' => '7', 'is_active' => true]);
        $santri = Santri::create([
            'nis' => 'S-'.$prefix, 'nama' => 'Santri Uji', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif', 'kelas_id' => $kelas->id,
        ]);
        $periode = PeriodeAkademik::create([
            'nama' => 'Ganjil', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => now()->subMonth(), 'tanggal_akhir' => now()->addMonths(5), 'is_active' => true,
        ]);
        $komponen = KomponenNilai::create(['nama' => 'UAS', 'bobot' => 100, 'urutan' => 1]);

        $svc = app(UjianAkademikService::class);
        $paket = $svc->buatPaketAkademik($guru, $pelajaran->id, ['nama' => 'UAS '.$prefix, 'durasi_menit' => 30]);
        $paket->soal()->createMany($soal);

        $sesi = SesiUjianAkademik::create([
            'paket_soal_id' => $paket->id, 'pelajaran_id' => $pelajaran->id,
            'kelas_id' => $kelas->id, 'periode_akademik_id' => $periode->id,
            'nama' => 'UAS 7A', 'mulai_at' => now()->subMinutes(5), 'selesai_at' => now()->addHours(2),
            'is_active' => true, 'created_by' => $guru->id,
        ]);

        return compact('guru', 'pelajaran', 'kelas', 'santri', 'periode', 'paket', 'sesi', 'komponen');
    }

    public function test_non_pengampu_tak_bisa_buat_paket_akademik_untuk_pelajaran_orang(): void
    {
        $ctx = $this->boot('f4a', []);

        $penyusup = User::withoutGlobalScopes()->create([
            'tenant_id' => tenant('id'), 'name' => 'Guru Lain',
            'email' => 'f4a-lain@test.local', 'password' => 'password123',
        ]);

        $this->expectException(AuthorizationException::class);
        app(UjianAkademikService::class)->buatPaketAkademik($penyusup, $ctx['pelajaran']->id, ['nama' => 'Curang']);
        tenancy()->end();
    }

    public function test_santri_kelas_lain_tak_bisa_attempt_sesi_rombel_sebelah(): void
    {
        $ctx = $this->boot('f4b', [
            ['tipe' => 'pg', 'pertanyaan' => 'A?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 1, 'sort' => 1],
        ]);

        $kelasLain = Kelas::create(['nama_kelas' => '7B', 'tingkat' => '7', 'is_active' => true]);
        $santriLain = Santri::create([
            'nis' => 'S-f4b-2', 'nama' => 'Santri Sebelah', 'tanggal_lahir' => '2012-02-02',
            'jenis_kelamin' => 'P', 'status' => 'aktif', 'kelas_id' => $kelasLain->id,
        ]);

        $this->expectException(AuthorizationException::class);
        app(UjianAkademikService::class)->mulai($santriLain, $ctx['sesi']);
        tenancy()->end();
    }

    public function test_santri_tak_bisa_dua_attempt_per_sesi(): void
    {
        $ctx = $this->boot('f4c', [
            ['tipe' => 'pg', 'pertanyaan' => 'A?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 1, 'sort' => 1],
        ]);
        $svc = app(UjianAkademikService::class);

        $attempt = $svc->mulai($ctx['santri'], $ctx['sesi']);
        // Resume = attempt sama, timer tidak reset.
        $this->assertSame($attempt->id, $svc->mulai($ctx['santri'], $ctx['sesi'])->id);

        $svc->jawab($attempt, $attempt->soal_tampil[0], 'A');
        $svc->selesaikan($attempt->fresh());

        $this->expectException(InvalidArgumentException::class);
        $svc->mulai($ctx['santri'], $ctx['sesi']);
        tenancy()->end();
    }

    public function test_nilai_essay_masuk_komponen_yang_benar_dan_tarik_idempoten(): void
    {
        $ctx = $this->boot('f4d', [
            ['tipe' => 'pg', 'pertanyaan' => 'P?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 2, 'sort' => 1],
            ['tipe' => 'essay', 'pertanyaan' => 'Uraikan.', 'bobot' => 8, 'sort' => 2],
        ]);
        $svc = app(UjianAkademikService::class);

        $attempt = $svc->mulai($ctx['santri'], $ctx['sesi']);
        [$pgId, $essayId] = $attempt->soal_tampil;
        $svc->jawab($attempt, $pgId, 'A'); // benar, bobot 2
        $svc->jawab($attempt->fresh(), $essayId, 'Jawaban panjang');

        $selesai = $svc->selesaikan($attempt->fresh());
        $this->assertSame('menunggu_nilai', $selesai->status);
        $this->assertNull($selesai->nilai_final);

        // Guru pengampu menilai essay 6/8 → final = (2+6)/10 × 100 = 80.
        $dinilai = $svc->nilaiEssay($ctx['guru'], $selesai, [$essayId => 6]);
        $this->assertEqualsWithDelta(80.0, (float) $dinilai->nilai_final, 0.01);

        // Tarik ke komponen UAS.
        $jumlah = $svc->tarikKeNilai($ctx['sesi'], $ctx['komponen']->id);
        $this->assertSame(1, $jumlah);

        $nilai = Nilai::query()
            ->where('santri_id', $ctx['santri']->id)
            ->where('pelajaran_id', $ctx['pelajaran']->id)
            ->where('periode_akademik_id', $ctx['periode']->id)
            ->get();
        $this->assertCount(1, $nilai);
        $this->assertEqualsWithDelta(80.0, (float) $nilai->first()->nilai_komponen[(string) $ctx['komponen']->id], 0.01);
        $this->assertEqualsWithDelta(80.0, (float) $nilai->first()->nilai_akhir, 0.01, 'Komponen UAS bobot 100 → akhir = 80.');

        // Idempoten: tarik 2× = baris & nilai sama.
        $svc->tarikKeNilai($ctx['sesi'], $ctx['komponen']->id);
        $ulang = Nilai::query()->where('santri_id', $ctx['santri']->id)->get();
        $this->assertCount(1, $ulang);
        $this->assertEqualsWithDelta(80.0, (float) $ulang->first()->nilai_komponen[(string) $ctx['komponen']->id], 0.01);
        tenancy()->end();
    }

    public function test_dua_jawab_berurutan_tidak_saling_menimpa(): void
    {
        $ctx = $this->boot('f4f', [
            ['tipe' => 'pg', 'pertanyaan' => 'P1?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 1, 'sort' => 1],
            ['tipe' => 'pg', 'pertanyaan' => 'P2?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'B', 'bobot' => 1, 'sort' => 2],
        ]);
        $svc = app(UjianAkademikService::class);

        $attempt = $svc->mulai($ctx['santri'], $ctx['sesi']);
        [$s1, $s2] = $attempt->soal_tampil;

        // Simulasi autosave: dua jawab dengan instance model BASI (snapshot
        // sebelum jawaban pertama tersimpan) — tanpa lock+re-read, jawab
        // kedua akan menimpa peta lama dan jawaban pertama hilang.
        $stale1 = UjianAttempt::query()->findOrFail($attempt->id);
        $stale2 = UjianAttempt::query()->findOrFail($attempt->id);
        $svc->jawab($stale1, $s1, 'A');
        $hasil = $svc->jawab($stale2, $s2, 'B');

        $map = (array) $hasil->jawaban;
        $this->assertSame('A', $map[(string) $s1] ?? null, 'Jawaban pertama harus tetap ada (merge, bukan timpa).');
        $this->assertSame('B', $map[(string) $s2] ?? null);
        tenancy()->end();
    }

    public function test_attempt_kedaluwarsa_difinalisasi_dan_ikut_tertarik_ke_nilai(): void
    {
        $ctx = $this->boot('f4g', [
            ['tipe' => 'pg', 'pertanyaan' => 'P?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 1, 'sort' => 1],
        ]);
        $svc = app(UjianAkademikService::class);

        $attempt = $svc->mulai($ctx['santri'], $ctx['sesi']);
        $svc->jawab($attempt, $attempt->soal_tampil[0], 'A'); // benar → 100

        // Deadline lewat, santri tidak pernah menekan "selesai".
        $attempt->forceFill(['deadline_at' => now()->subMinute()])->save();
        $this->assertSame('berjalan', $attempt->fresh()->status);

        $jumlah = $svc->tarikKeNilai($ctx['sesi'], $ctx['komponen']->id);
        $this->assertSame(1, $jumlah, 'Attempt kedaluwarsa harus difinalisasi lalu ikut tertarik.');

        $segar = $attempt->fresh();
        $this->assertSame('selesai', $segar->status);
        $this->assertEqualsWithDelta(100.0, (float) $segar->nilai_final, 0.01);

        $nilai = Nilai::query()->where('santri_id', $ctx['santri']->id)->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $nilai->nilai_komponen[(string) $ctx['komponen']->id], 0.01);
        tenancy()->end();
    }

    public function test_tarik_nilai_menolak_komponen_pelajaran_lain(): void
    {
        $ctx = $this->boot('f4h', [
            ['tipe' => 'pg', 'pertanyaan' => 'P?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A', 'bobot' => 1, 'sort' => 1],
        ]);
        $svc = app(UjianAkademikService::class);

        $attempt = $svc->mulai($ctx['santri'], $ctx['sesi']);
        $svc->jawab($attempt, $attempt->soal_tampil[0], 'A');
        $svc->selesaikan($attempt->fresh());

        // Komponen milik pelajaran LAIN — bukan anggota daftar komponen
        // pelajaran sesi (sesi pakai komponen global karena tidak ada
        // komponen spesifik pelajarannya... maka buat komponen spesifik lain).
        $pelajaranLain = Pelajaran::create(['nama' => 'Fiqih', 'kode' => 'FQH', 'guru_pengampu_id' => $ctx['guru']->id]);
        $komponenLain = KomponenNilai::create([
            'nama' => 'UAS Fiqih', 'bobot' => 100, 'urutan' => 9, 'pelajaran_id' => $pelajaranLain->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $svc->tarikKeNilai($ctx['sesi'], $komponenLain->id);
        tenancy()->end();
    }

    public function test_guru_lain_tak_bisa_nilai_essay_paket_orang(): void
    {
        $ctx = $this->boot('f4e', [
            ['tipe' => 'essay', 'pertanyaan' => 'Uraikan.', 'bobot' => 5, 'sort' => 1],
        ]);
        $svc = app(UjianAkademikService::class);

        $attempt = $svc->mulai($ctx['santri'], $ctx['sesi']);
        $svc->jawab($attempt, $attempt->soal_tampil[0], 'Jawaban');
        $selesai = $svc->selesaikan($attempt->fresh());

        $guruLain = User::withoutGlobalScopes()->create([
            'tenant_id' => tenant('id'), 'name' => 'Guru Asing',
            'email' => 'f4e-asing@test.local', 'password' => 'password123',
        ]);

        $this->expectException(AuthorizationException::class);
        $svc->nilaiEssay($guruLain, $selesai, [$attempt->soal_tampil[0] => 5]);
        tenancy()->end();
    }
}
