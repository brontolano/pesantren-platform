<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Extends TenantDemoSeeder with data for Portal Wali v2 demo.
 * Run AFTER TenantDemoSeeder, inside tenant context:
 *   Tenant::find('demo')->run(fn () => (new PortalWaliDemoSeeder)->run());
 */
class PortalWaliDemoSeeder extends Seeder
{
    public function run(): void
    {
        $t = 'demo';
        $now = now();

        $wali = DB::table('users')->where('email', 'wali@demo.pesantren.online')->first();
        if (! $wali) {
            $this->command?->error('Wali user not found — run TenantDemoSeeder first.');
            return;
        }

        $santri1 = DB::table('santri')->where('tenant_id', $t)->where('nis', '2024001')->first();
        $santri2 = DB::table('santri')->where('tenant_id', $t)->where('nis', '2024003')->first(); // Aisyah Putri

        if (! $santri1 || ! $santri2) {
            $this->command?->error('Santri demo not found.');
            return;
        }

        // --- Link second child to wali ---
        DB::table('access_wali')->updateOrInsert(
            ['tenant_id' => $t, 'wali_id' => $wali->id, 'santri_id' => $santri2->id],
            ['relationship_type' => 'orang_tua', 'is_primary' => false, 'created_at' => $now, 'updated_at' => $now]
        );

        // Tabungan for second child
        DB::table('tabungan')->updateOrInsert(
            ['tenant_id' => $t, 'santri_id' => $santri2->id],
            ['saldo' => 350000, 'is_frozen' => false, 'created_at' => $now, 'updated_at' => $now]
        );

        $periode = DB::table('periode_akademik')->where('tenant_id', $t)->where('is_active', true)->first();
        $periodeId = $periode?->id;
        $pelajaranIds = DB::table('pelajaran')->where('tenant_id', $t)->pluck('id', 'kode')->all();
        $kelasIds = DB::table('kelas')->where('tenant_id', $t)->pluck('id', 'nama_kelas')->all();

        $kepala = DB::table('users')->where('email', 'kepala@demo.pesantren.online')->first();

        foreach ([$santri1, $santri2] as $idx => $santri) {
            $this->seedHafalan($t, $santri, $now, $idx);
            $this->seedNilaiRapor($t, $santri, $periodeId, $pelajaranIds, $now, $idx);
            $this->seedKesehatan($t, $santri, $now, $idx);
            $this->seedIzin($t, $santri, $now, $idx, $kepala?->id);
        }

        $this->command?->info('Portal Wali demo data seeded for 2 children.');
    }

    private function seedHafalan(string $t, object $santri, $now, int $idx): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tahfidz_hafalan')) return;

        $surahs = [
            ['surah' => 'Al-Baqarah', 'ayat_awal' => 1, 'ayat_akhir' => 20, 'status' => 'selesai'],
            ['surah' => 'Al-Baqarah', 'ayat_awal' => 21, 'ayat_akhir' => 40, 'status' => 'selesai'],
            ['surah' => 'Al-Baqarah', 'ayat_awal' => 41, 'ayat_akhir' => 60, 'status' => 'berjalan'],
            ['surah' => 'Al-Mulk', 'ayat_awal' => 1, 'ayat_akhir' => 30, 'status' => 'selesai'],
            ['surah' => 'Yasin', 'ayat_awal' => 1, 'ayat_akhir' => 40, 'status' => 'selesai'],
        ];

        if ($idx === 1) {
            $surahs = [
                ['surah' => 'Al-Mulk', 'ayat_awal' => 1, 'ayat_akhir' => 30, 'status' => 'selesai'],
                ['surah' => 'Ar-Rahman', 'ayat_awal' => 1, 'ayat_akhir' => 30, 'status' => 'selesai'],
                ['surah' => 'Ar-Rahman', 'ayat_awal' => 31, 'ayat_akhir' => 78, 'status' => 'berjalan'],
            ];
        }

        $nilaiOptions = [95, 88, 92, 85, 90];
        $jenisOptions = ['ziyadah', 'murajaah', 'ziyadah', 'murajaah', 'ziyadah'];

        foreach ($surahs as $i => $s) {
            $hafalanId = DB::table('tahfidz_hafalan')->insertGetId([
                'tenant_id' => $t,
                'santri_id' => $santri->id,
                'surah' => $s['surah'],
                'ayat_awal' => $s['ayat_awal'],
                'ayat_akhir' => $s['ayat_akhir'],
                'tanggal_target' => now()->subDays(30 - $i * 5)->toDateString(),
                'tanggal_selesai' => $s['status'] === 'selesai' ? now()->subDays(20 - $i * 4)->toDateString() : null,
                'status' => $s['status'],
                'catatan' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (DB::getSchemaBuilder()->hasTable('tahfidz_penilaian')) {
                DB::table('tahfidz_penilaian')->insert([
                    'tenant_id' => $t,
                    'santri_id' => $santri->id,
                    'hafalan_id' => $hafalanId,
                    'jenis' => $jenisOptions[$i % count($jenisOptions)],
                    'nilai' => $nilaiOptions[$i % count($nilaiOptions)],
                    'catatan' => null,
                    'tanggal' => now()->subDays(20 - $i * 4)->toDateString(),
                    'input_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedNilaiRapor(string $t, object $santri, ?int $periodeId, array $pelajaranIds, $now, int $idx): void
    {
        if (! $periodeId || ! DB::getSchemaBuilder()->hasTable('rapor')) return;

        $kelasId = $santri->kelas_id;
        $ipk = $idx === 0 ? 87.5 : 91.2;
        $rank = $idx === 0 ? 5 : 2;

        DB::table('rapor')->updateOrInsert(
            ['santri_id' => $santri->id, 'periode_akademik_id' => $periodeId],
            [
                'tenant_id' => $t,
                'total_sks' => 21,
                'ipk' => $ipk,
                'rank_kelas' => $rank,
                'catatan_walikelas' => $idx === 0
                    ? 'Ahmad menunjukkan perkembangan baik. Perlu ditingkatkan di bidang Matematika.'
                    : 'Aisyah sangat rajin dan aktif. Prestasi stabil di semua mata pelajaran.',
                'ringkasan_nilai' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if (! DB::getSchemaBuilder()->hasTable('nilai')) return;

        $nilaiSet = $idx === 0
            ? ['FQH' => 88, 'AQD' => 85, 'QHD' => 92, 'BAR' => 80, 'NHS' => 78, 'MTK' => 75, 'BIN' => 88, 'IPA' => 82]
            : ['FQH' => 95, 'AQD' => 92, 'QHD' => 94, 'BAR' => 88, 'NHS' => 85, 'MTK' => 90, 'BIN' => 91, 'IPA' => 93];

        foreach ($nilaiSet as $kode => $nilaiAkhir) {
            if (! isset($pelajaranIds[$kode])) continue;
            DB::table('nilai')->updateOrInsert(
                ['santri_id' => $santri->id, 'pelajaran_id' => $pelajaranIds[$kode], 'periode_akademik_id' => $periodeId],
                [
                    'tenant_id' => $t,
                    'kelas_id' => $kelasId,
                    'nilai_harian' => $nilaiAkhir - rand(0, 5),
                    'nilai_uts' => $nilaiAkhir - rand(0, 3),
                    'nilai_uas' => $nilaiAkhir + rand(-2, 3),
                    'nilai_akhir' => $nilaiAkhir,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedKesehatan(string $t, object $santri, $now, int $idx): void
    {
        if (! DB::getSchemaBuilder()->hasTable('riwayat_kesehatan')) return;

        $riwayat = $idx === 0
            ? [
                ['keluhan' => 'Demam dan batuk', 'diagnosa' => 'Influenza ringan', 'tindakan' => 'Istirahat + obat', 'kondisi' => 'sembuh', 'tanggal' => now()->subDays(15)->toDateString()],
                ['keluhan' => 'Sakit perut', 'diagnosa' => 'Dispepsia', 'tindakan' => 'Antasida + diet', 'kondisi' => 'sembuh', 'tanggal' => now()->subDays(45)->toDateString()],
                ['keluhan' => 'Pemeriksaan rutin', 'diagnosa' => 'Sehat', 'tindakan' => 'Tidak ada', 'kondisi' => 'sehat', 'tanggal' => now()->subDays(60)->toDateString()],
            ]
            : [
                ['keluhan' => 'Pusing', 'diagnosa' => 'Kurang tidur', 'tindakan' => 'Istirahat', 'kondisi' => 'sembuh', 'tanggal' => now()->subDays(10)->toDateString()],
                ['keluhan' => 'Pemeriksaan rutin', 'diagnosa' => 'Sehat', 'tindakan' => 'Tidak ada', 'kondisi' => 'sehat', 'tanggal' => now()->subDays(30)->toDateString()],
            ];

        foreach ($riwayat as $r) {
            DB::table('riwayat_kesehatan')->updateOrInsert(
                ['santri_id' => $santri->id, 'tanggal' => $r['tanggal'], 'keluhan' => $r['keluhan']],
                array_merge($r, [
                    'tenant_id' => $t,
                    'santri_id' => $santri->id,
                    'petugas' => 'Ust. Hasan (UKS)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    private function seedIzin(string $t, object $santri, $now, int $idx, ?int $approvedBy = null): void
    {
        if (! DB::getSchemaBuilder()->hasTable('izin_santri')) return;

        $izinData = $idx === 0
            ? [
                ['jenis_izin' => 'sakit', 'tanggal_mulai' => now()->subDays(14)->toDateString(), 'tanggal_selesai' => now()->subDays(12)->toDateString(), 'alasan' => 'Demam tinggi, perlu perawatan di rumah', 'status' => 'disetujui'],
                ['jenis_izin' => 'pulang', 'tanggal_mulai' => now()->subDays(30)->toDateString(), 'tanggal_selesai' => now()->subDays(28)->toDateString(), 'alasan' => 'Acara keluarga (pernikahan kakak)', 'status' => 'selesai'],
                ['jenis_izin' => 'khusus', 'tanggal_mulai' => now()->addDays(5)->toDateString(), 'tanggal_selesai' => now()->addDays(5)->toDateString(), 'alasan' => 'Kontrol ke dokter gigi', 'status' => 'pending'],
            ]
            : [
                ['jenis_izin' => 'pulang', 'tanggal_mulai' => now()->subDays(20)->toDateString(), 'tanggal_selesai' => now()->subDays(18)->toDateString(), 'alasan' => 'Pulang bulanan', 'status' => 'disetujui'],
                ['jenis_izin' => 'sakit', 'tanggal_mulai' => now()->subDays(8)->toDateString(), 'tanggal_selesai' => now()->subDays(7)->toDateString(), 'alasan' => 'Pusing dan kurang enak badan', 'status' => 'ditolak'],
            ];

        foreach ($izinData as $izin) {
            DB::table('izin_santri')->updateOrInsert(
                ['santri_id' => $santri->id, 'jenis_izin' => $izin['jenis_izin'], 'tanggal_mulai' => $izin['tanggal_mulai']],
                array_merge($izin, [
                    'tenant_id' => $t,
                    'santri_id' => $santri->id,
                    'disetujui_oleh' => $izin['status'] === 'disetujui' ? $approvedBy : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
