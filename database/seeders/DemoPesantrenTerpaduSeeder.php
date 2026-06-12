<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder demo terpadu: santri ↔ wali ↔ kelas ↔ asrama ↔ SDM ↔ akademik ↔ tahfidz ↔ disiplin ↔ tabungan.
 * Idempotent — aman dijalankan ulang. Jalankan dalam konteks tenant (tinker).
 */
class DemoPesantrenTerpaduSeeder extends Seeder
{
    private string $tid;

    public function run(): void
    {
        $this->tid = tenant()->getTenantKey();

        $this->lengkapiSantri();
        $this->buatAkunWali();
        $this->penempatanKelas();
        $this->kamarDanPenempatanAsrama();
        $this->sdmPegawai();
        $this->jadwalPelajaran();
        $this->presensiSantri();
        $this->nilaiSantri();
        $this->tahfidz();
        $this->disiplin();
        $this->tabunganSantri();
        $this->presensiStaf();

        $this->command?->info('Demo terpadu selesai.');
    }

    /** Lengkapi field santri yang kosong (orang tua, alamat, angkatan, kelas). */
    private function lengkapiSantri(): void
    {
        $kelasIds = DB::table('kelas')->orderBy('id')->pluck('id')->toArray();
        $santri = DB::table('santri')->whereNull('deleted_at')->orderBy('id')->get();

        $ayah = ['Budi Santoso', 'Ahmad Fauzi', 'Dedi Kurniawan', 'Hasan Basri', 'Slamet Riyadi', 'Joko Susilo', 'Usman Hakim', 'Rahmat Hidayat', 'Agus Salim', 'Yusuf Mansur'];
        $ibu = ['Siti Aminah', 'Nur Hasanah', 'Dewi Lestari', 'Fatimah Zahra', 'Sri Wahyuni', 'Umi Kulsum', 'Aisyah Putri', 'Rina Marlina', 'Khodijah', 'Maryam Salsabila'];
        $kerja = ['Petani', 'Wiraswasta', 'Guru', 'Pedagang', 'PNS', 'Buruh', 'Sopir', 'Karyawan Swasta'];
        $desa = ['Cimalaka', 'Tanjungsari', 'Situraja', 'Paseh', 'Conggeang', 'Buahdua'];

        foreach ($santri as $i => $s) {
            $upd = [];
            if (empty($s->nama_ayah)) {
                $upd['nama_ayah'] = $ayah[$i % count($ayah)];
            }
            if (empty($s->nama_ibu)) {
                $upd['nama_ibu'] = $ibu[$i % count($ibu)];
            }
            if (empty($s->pekerjaan_ayah)) {
                $upd['pekerjaan_ayah'] = $kerja[$i % count($kerja)];
            }
            if (empty($s->pekerjaan_ibu)) {
                $upd['pekerjaan_ibu'] = 'Ibu Rumah Tangga';
            }
            if (empty($s->no_hp_wali)) {
                $upd['no_hp_wali'] = '0852' . str_pad((string) (10000000 + $i * 137), 8, '0');
            }
            if (empty($s->alamat)) {
                $upd['alamat'] = 'Dusun ' . $desa[$i % count($desa)] . ' RT 0' . (($i % 5) + 1) . '/RW 0' . (($i % 3) + 1);
            }
            if (empty($s->desa_kelurahan)) {
                $upd['desa_kelurahan'] = $desa[$i % count($desa)];
            }
            if (empty($s->kecamatan)) {
                $upd['kecamatan'] = $desa[($i + 2) % count($desa)];
            }
            if (empty($s->kabupaten_kota)) {
                $upd['kabupaten_kota'] = 'Sumedang';
            }
            if (empty($s->provinsi)) {
                $upd['provinsi'] = 'Jawa Barat';
            }
            if (empty($s->tahun_angkatan)) {
                $upd['tahun_angkatan'] = '2026/2027';
            }
            if (empty($s->kelas_id) && $kelasIds) {
                $upd['kelas_id'] = $kelasIds[$i % count($kelasIds)];
            }
            if (empty($s->tempat_lahir)) {
                $upd['tempat_lahir'] = 'Sumedang';
            }

            if ($upd) {
                $upd['updated_at'] = now();
                DB::table('santri')->where('id', $s->id)->update($upd);
            }
        }
    }

    /** Buat akun wali (1 akun bisa beberapa anak: pasangan kakak-adik tiap 6 santri pertama). */
    private function buatAkunWali(): void
    {
        $santri = DB::table('santri')->whereNull('deleted_at')->orderBy('id')->get();
        $waliRoleId = DB::table('roles')->where('name', 'wali')->value('id');

        foreach ($santri as $i => $s) {
            if ($s->wali_user_id) {
                continue;
            }

            // Tiap pasangan (0-1, 2-3, ...) di 6 santri pertama berbagi 1 wali (kakak-adik)
            $shareWith = ($i < 6 && $i % 2 === 1) ? $santri[$i - 1] : null;
            if ($shareWith && $shareWith->wali_user_id) {
                $waliId = $shareWith->wali_user_id;
            } else {
                $email = 'wali.' . strtolower(preg_replace('/[^a-z0-9]/i', '', $s->nis ?: ('s' . $s->id))) . '@demo.pesantren.online';
                $waliId = DB::table('users')->where('email', $email)->value('id');
                if (! $waliId) {
                    $waliId = DB::table('users')->insertGetId([
                        'tenant_id' => $this->tid,
                        'name' => 'Bpk. ' . ($s->nama_ayah ?: 'Wali ' . $s->nama),
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($waliRoleId) {
                        DB::table('model_has_roles')->insertOrIgnore([
                            'role_id' => $waliRoleId,
                            'model_type' => 'App\\Models\\User',
                            'model_id' => $waliId,
                        ]);
                    }
                }
            }

            DB::table('santri')->where('id', $s->id)->update(['wali_user_id' => $waliId, 'updated_at' => now()]);

            $exists = DB::table('access_wali')->where('wali_id', $waliId)->where('santri_id', $s->id)->exists();
            if (! $exists) {
                DB::table('access_wali')->insert([
                    'tenant_id' => $this->tid,
                    'wali_id' => $waliId,
                    'santri_id' => $s->id,
                    'relationship_type' => 'orang_tua',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function penempatanKelas(): void
    {
        if (DB::table('penempatan_kelas')->count() > 0) {
            return;
        }

        $santri = DB::table('santri')->whereNull('deleted_at')->whereNotNull('kelas_id')->get();
        foreach ($santri as $s) {
            DB::table('penempatan_kelas')->insert([
                'tenant_id' => $this->tid,
                'santri_id' => $s->id,
                'kelas_id' => $s->kelas_id,
                'semester' => 'Ganjil',
                'tahun_akademik' => '2026/2027',
                'tanggal_penempatan' => '2026-07-15',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function kamarDanPenempatanAsrama(): void
    {
        $blokIds = DB::table('blok_asrama')->orderBy('id')->pluck('id')->toArray();
        if (! $blokIds) {
            return;
        }

        if (DB::table('kamar_asrama')->count() === 0) {
            foreach ($blokIds as $b => $blokId) {
                foreach (range(1, 4) as $n) {
                    DB::table('kamar_asrama')->insert([
                        'tenant_id' => $this->tid,
                        'blok_id' => $blokId,
                        'nomor_kamar' => chr(65 + $b) . '-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                        'kapasitas' => 8,
                        'tipe' => 'regular',
                        'biaya_per_bulan' => 150000,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (DB::table('penempatan_asrama')->count() > 0) {
            return;
        }

        $kamarIds = DB::table('kamar_asrama')->orderBy('id')->pluck('id')->toArray();
        $santri = DB::table('santri')->whereNull('deleted_at')->where('is_mukim', true)->orderBy('id')->get();

        foreach ($santri as $i => $s) {
            DB::table('penempatan_asrama')->insert([
                'tenant_id' => $this->tid,
                'santri_id' => $s->id,
                'kamar_id' => $kamarIds[$i % count($kamarIds)],
                'tanggal_masuk' => '2026-07-15',
                'status' => 'aktif',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Employee + penugasan + kontrak untuk semua user staf (selain wali). */
    private function sdmPegawai(): void
    {
        if (DB::table('employee')->count() > 0) {
            return;
        }

        $lembagaId = DB::table('lembaga')->value('id');
        $staf = DB::table('users')
            ->whereNotIn('id', DB::table('access_wali')->pluck('wali_id'))
            ->orderBy('id')->limit(7)->get();

        $jabatanMap = [
            ['cari' => 'kepala', 'status' => 'tetap'],
            ['cari' => 'guru', 'status' => 'tetap'],
            ['cari' => 'guru', 'status' => 'tetap'],
            ['cari' => 'bendahara', 'status' => 'tetap'],
            ['cari' => 'tu', 'status' => 'honorer'],
            ['cari' => 'staf', 'status' => 'honorer'],
            ['cari' => 'staf', 'status' => 'honorer'],
        ];

        foreach ($staf as $i => $u) {
            $empId = DB::table('employee')->insertGetId([
                'tenant_id' => $this->tid,
                'users_id' => $u->id,
                'nip' => 'PEG-2026-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'nama' => $u->name,
                'status_kepegawaian' => $jabatanMap[$i % 7]['status'],
                'ptkp_status' => $i % 2 === 0 ? 'K1' : 'TK0',
                'tanggal_masuk' => '2024-07-01',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $jabatanId = DB::table('jabatan')
                ->where('nama', 'ilike', '%' . $jabatanMap[$i % 7]['cari'] . '%')
                ->value('id') ?? DB::table('jabatan')->value('id');

            if ($jabatanId) {
                DB::table('penugasan_pegawai')->insert([
                    'tenant_id' => $this->tid,
                    'employee_id' => $empId,
                    'jabatan_id' => $jabatanId,
                    'lembaga_id' => $lembagaId,
                    'tanggal_mulai' => '2026-07-01',
                    'nomor_sk' => 'SK/DEMO/2026/' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('kontrak_kerja')->insert([
                'tenant_id' => $this->tid,
                'employee_id' => $empId,
                'jenis' => $jabatanMap[$i % 7]['status'] === 'tetap' ? 'sk_gty' : 'kontrak',
                'nomor' => 'KK/DEMO/2026/' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'mulai' => '2026-07-01',
                'selesai' => $jabatanMap[$i % 7]['status'] === 'tetap' ? null : '2027-06-30',
                'status' => 'aktif',
                'periode_khidmat' => '2026/2027',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function jadwalPelajaran(): void
    {
        if (DB::table('jadwal_pelajaran')->count() > 0) {
            return;
        }

        $kelasIds = DB::table('kelas')->orderBy('id')->pluck('id')->toArray();
        $pelajaran = DB::table('pelajaran')->orderBy('id')->get();
        $periodeId = DB::table('periode_akademik')->value('id');
        $guruIds = DB::table('users')
            ->whereIn('id', DB::table('employee')->whereNotNull('users_id')->pluck('users_id'))
            ->pluck('id')->toArray();

        if (! $kelasIds || $pelajaran->isEmpty()) {
            return;
        }

        // Pastikan tiap pelajaran punya guru pengampu
        foreach ($pelajaran as $i => $p) {
            if (empty($p->guru_pengampu_id) && $guruIds) {
                DB::table('pelajaran')->where('id', $p->id)
                    ->update(['guru_pengampu_id' => $guruIds[$i % count($guruIds)], 'updated_at' => now()]);
            }
        }

        $hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Sabtu'];
        $slot = [['07:30', '09:00'], ['09:15', '10:45'], ['11:00', '12:30']];

        foreach ($kelasIds as $k => $kelasId) {
            foreach ($pelajaran->take(8) as $i => $p) {
                $h = $hari[$i % count($hari)];
                $s = $slot[($i + $k) % count($slot)];
                DB::table('jadwal_pelajaran')->insert([
                    'tenant_id' => $this->tid,
                    'kelas_id' => $kelasId,
                    'pelajaran_id' => $p->id,
                    'periode_akademik_id' => $periodeId,
                    'hari' => $h,
                    'jam_mulai' => $s[0],
                    'jam_selesai' => $s[1],
                    'ruangan' => 'R-' . ($k + 1) . '0' . (($i % 3) + 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function presensiSantri(): void
    {
        if (DB::table('presensi_santri')->count() > 0) {
            return;
        }

        $santri = DB::table('santri')->whereNull('deleted_at')->get();
        $adminId = DB::table('users')->orderBy('id')->value('id');

        foreach (range(0, 6) as $d) {
            $tgl = now()->subDays($d);
            if (in_array($tgl->dayOfWeek, [5], true)) {
                continue; // Jumat libur
            }
            foreach ($santri as $i => $s) {
                // ~90% hadir
                $r = ($i + $d) % 12;
                $status = match (true) {
                    $r === 10 => 'izin',
                    $r === 11 => 'sakit',
                    default => 'hadir',
                };
                DB::table('presensi_santri')->insertOrIgnore([
                    'tenant_id' => $this->tid,
                    'santri_id' => $s->id,
                    'kelas_id' => $s->kelas_id,
                    'tanggal' => $tgl->toDateString(),
                    'status' => $status,
                    'dicatat_oleh' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function nilaiSantri(): void
    {
        if (DB::table('nilai')->count() > 0) {
            return;
        }

        $santri = DB::table('santri')->whereNull('deleted_at')->whereNotNull('kelas_id')->get();
        $pelajaranIds = DB::table('pelajaran')->orderBy('id')->limit(5)->pluck('id')->toArray();
        $periodeId = DB::table('periode_akademik')->value('id');
        $adminId = DB::table('users')->orderBy('id')->value('id');

        if (! $periodeId || ! $pelajaranIds) {
            return;
        }

        foreach ($santri as $i => $s) {
            foreach ($pelajaranIds as $j => $pid) {
                $base = 70 + (($i * 7 + $j * 11) % 25); // 70-94 deterministik
                $harian = $base + 2;
                $uts = $base - 3;
                $uas = $base;
                DB::table('nilai')->insertOrIgnore([
                    'tenant_id' => $this->tid,
                    'santri_id' => $s->id,
                    'pelajaran_id' => $pid,
                    'periode_akademik_id' => $periodeId,
                    'kelas_id' => $s->kelas_id,
                    'nilai_harian' => $harian,
                    'nilai_uts' => $uts,
                    'nilai_uas' => $uas,
                    'nilai_akhir' => round(($harian + $uts + $uas) / 3, 2),
                    'input_by' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function tahfidz(): void
    {
        $santri = DB::table('santri')->whereNull('deleted_at')->orderBy('id')->limit(10)->get();

        if (DB::table('tahfidz_target')->count() === 0) {
            foreach ($santri as $i => $s) {
                DB::table('tahfidz_target')->insertOrIgnore([
                    'tenant_id' => $this->tid,
                    'santri_id' => $s->id,
                    'periode' => '2026-S1',
                    'target_surah' => 5 + ($i % 6),
                    'keterangan' => 'Target semester ganjil 2026/2027',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (DB::table('tahfidz_hafalan')->count() > 0) {
            return;
        }

        $surah = [
            ['An-Naba', 40], ['An-Naziat', 46], ['Abasa', 42], ['At-Takwir', 29],
            ['Al-Infitar', 19], ['Al-Mutaffifin', 36], ['Al-Insyiqaq', 25], ['Al-Buruj', 22],
        ];

        foreach ($santri as $i => $s) {
            foreach (range(0, 2) as $j) {
                $idx = ($i + $j) % count($surah);
                $selesai = $j < 2;
                DB::table('tahfidz_hafalan')->insert([
                    'tenant_id' => $this->tid,
                    'santri_id' => $s->id,
                    'surah' => $surah[$idx][0],
                    'ayat_awal' => 1,
                    'ayat_akhir' => $surah[$idx][1],
                    'tanggal_target' => now()->addDays(14 + $j * 14)->toDateString(),
                    'tanggal_selesai' => $selesai ? now()->subDays(20 - $j * 10)->toDateString() : null,
                    'status' => $selesai ? 'selesai' : 'sedang_hafalan',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function disiplin(): void
    {
        if (DB::table('pelanggaran_santri')->count() > 0) {
            return;
        }

        $santriIds = DB::table('santri')->whereNull('deleted_at')->orderBy('id')->pluck('id')->toArray();
        $adminId = DB::table('users')->orderBy('id')->value('id');
        $jenisRingan = DB::table('jenis_pelanggaran')->where('kategori', 'ringan')->value('id');
        $jenisSedang = DB::table('jenis_pelanggaran')->where('kategori', 'sedang')->value('id');
        $jenisPrestasi = DB::table('jenis_pelanggaran')->where('kategori', 'prestasi')->value('id');

        $rows = [];
        if ($jenisRingan && isset($santriIds[3])) {
            $rows[] = ['santri_id' => $santriIds[3], 'jenis' => $jenisRingan, 'kronologi' => 'Terlambat masuk kelas setelah istirahat', 'status' => 'selesai', 'hari' => 5];
            $rows[] = ['santri_id' => $santriIds[7] ?? $santriIds[0], 'jenis' => $jenisRingan, 'kronologi' => 'Tidak memakai seragam lengkap saat apel pagi', 'status' => 'diverifikasi', 'hari' => 2];
        }
        if ($jenisSedang && isset($santriIds[5])) {
            $rows[] = ['santri_id' => $santriIds[5], 'jenis' => $jenisSedang, 'kronologi' => 'Membawa HP tanpa izin ke asrama', 'status' => 'diverifikasi', 'hari' => 3];
        }
        if ($jenisPrestasi && isset($santriIds[1])) {
            $rows[] = ['santri_id' => $santriIds[1], 'jenis' => $jenisPrestasi, 'kronologi' => 'Juara 1 MHQ tingkat kecamatan', 'status' => 'diverifikasi', 'hari' => 7];
        }

        foreach ($rows as $r) {
            DB::table('pelanggaran_santri')->insert([
                'tenant_id' => $this->tid,
                'santri_id' => $r['santri_id'],
                'jenis_pelanggaran_id' => $r['jenis'],
                'waktu_kejadian' => now()->subDays($r['hari']),
                'lokasi' => 'Lingkungan pesantren',
                'kronologi' => $r['kronologi'],
                'pencatat_id' => $adminId,
                'status' => $r['status'],
                'verifikator_id' => $r['status'] !== 'dicatat' ? $adminId : null,
                'verified_at' => $r['status'] !== 'dicatat' ? now()->subDays($r['hari'] - 1) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function tabunganSantri(): void
    {
        $santri = DB::table('santri')->whereNull('deleted_at')->orderBy('id')->get();
        $adminId = DB::table('users')->orderBy('id')->value('id');

        foreach ($santri as $i => $s) {
            $tabunganId = DB::table('tabungan')->where('santri_id', $s->id)->value('id');
            if ($tabunganId) {
                continue; // sudah punya rekening + transaksi
            }

            $tabunganId = DB::table('tabungan')->insertGetId([
                'tenant_id' => $this->tid,
                'santri_id' => $s->id,
                'saldo' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $saldo = 0;
            $trx = [
                ['tipe' => 'setor', 'nominal' => 100000 + ($i % 4) * 50000, 'ket' => 'Setoran awal dari wali', 'hari' => 20],
                ['tipe' => 'setor', 'nominal' => 50000, 'ket' => 'Kiriman bulanan', 'hari' => 10],
                ['tipe' => 'tarik', 'nominal' => 25000, 'ket' => 'Jajan kantin & keperluan mandi', 'hari' => 4],
            ];

            foreach ($trx as $t) {
                $sebelum = $saldo;
                $saldo = $t['tipe'] === 'setor' ? $saldo + $t['nominal'] : $saldo - $t['nominal'];
                DB::table('tabungan_transaksi')->insert([
                    'tenant_id' => $this->tid,
                    'tabungan_id' => $tabunganId,
                    'tipe' => $t['tipe'],
                    'nominal' => $t['nominal'],
                    'saldo_sebelum' => $sebelum,
                    'saldo_sesudah' => $saldo,
                    'keterangan' => $t['ket'],
                    'tanggal' => now()->subDays($t['hari'])->toDateString(),
                    'created_by' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('tabungan')->where('id', $tabunganId)->update(['saldo' => $saldo, 'updated_at' => now()]);
        }
    }

    private function presensiStaf(): void
    {
        if (DB::table('presensi_staf')->count() > 0) {
            return;
        }

        $empIds = DB::table('employee')->pluck('id')->toArray();

        foreach (range(0, 6) as $d) {
            $tgl = now()->subDays($d);
            if ($tgl->dayOfWeek === 5) {
                continue;
            }
            foreach ($empIds as $i => $eid) {
                $status = (($i + $d) % 14 === 13) ? 'izin' : 'hadir';
                DB::table('presensi_staf')->insertOrIgnore([
                    'tenant_id' => $this->tid,
                    'employee_id' => $eid,
                    'tanggal' => $tgl->toDateString(),
                    'status' => $status,
                    'jam_masuk' => $status === 'hadir' ? '07:0' . ($i % 6) : null,
                    'jam_keluar' => $status === 'hadir' ? '15:3' . ($i % 6) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
