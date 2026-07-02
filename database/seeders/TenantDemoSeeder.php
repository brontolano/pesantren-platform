<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder data contoh untuk tenant "demo".
 * WAJIB dijalankan dalam konteks tenancy demo (koneksi default = tenantdemo),
 * mis. via: Tenant::find('demo')->run(fn () => (new TenantDemoSeeder())->run());
 */
class TenantDemoSeeder extends Seeder
{
    public function run(): void
    {
        $t = 'demo';
        $now = now();

        // 0. Record tenant sendiri di DB tenant (agar FK tenant_id resolve)
        DB::table('tenants')->updateOrInsert(
            ['id' => $t],
            ['name' => 'Pesantren Demo', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );

        // 1. Roles & permissions (pakai seeder yang sudah ada)
        (new RolesAndPermissionsSeeder)->run();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Users
        $users = [
            ['name' => 'Admin Demo', 'email' => 'admin@demo.pesantren.online', 'role' => 'super_admin'],
            ['name' => 'KH. Ahmad Fauzi', 'email' => 'kepala@demo.pesantren.online', 'role' => 'kepala'],
            ['name' => 'Siti Aisyah', 'email' => 'tu@demo.pesantren.online', 'role' => 'tata_usaha'],
            ['name' => 'Hj. Nurjanah', 'email' => 'bendahara@demo.pesantren.online', 'role' => 'bendahara'],
        ];
        foreach ($users as $u) {
            $user = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('demo1234'), 'tenant_id' => $t]
            );
            if (Role::where('name', $u['role'])->exists()) {
                $user->syncRoles([$u['role']]);
            }
        }

        // 3. Settings branding
        $settings = [
            'branding_name' => 'Pesantren Demo',
            'branding_tagline' => 'Mencetak Generasi Qurani Berakhlak Mulia',
            'contact_phone' => '+62 812-3456-7890',
            'contact_email' => 'info@demo.pesantren.online',
            'contact_address' => 'Jl. Pesantren No. 1, Sumedang, Jawa Barat',
        ];
        foreach ($settings as $k => $v) {
            DB::table('settings')->updateOrInsert(
                ['tenant_id' => $t, 'key' => $k],
                ['value' => json_encode(['text' => $v]), 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // 4. Periode akademik
        DB::table('periode_akademik')->updateOrInsert(
            ['tenant_id' => $t, 'nama' => '2025/2026 Genap'],
            ['tahun_akademik' => '2025/2026', 'semester' => 'genap', 'tanggal_mulai' => '2026-01-06', 'tanggal_akhir' => '2026-06-20', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );

        // 5. Kelas
        $kelasData = [
            ['nama_kelas' => 'VII-A', 'tingkat' => '7', 'capacity' => 30],
            ['nama_kelas' => 'VII-B', 'tingkat' => '7', 'capacity' => 30],
            ['nama_kelas' => 'VIII-A', 'tingkat' => '8', 'capacity' => 30],
            ['nama_kelas' => 'IX-A', 'tingkat' => '9', 'capacity' => 25],
        ];
        foreach ($kelasData as $k) {
            DB::table('kelas')->updateOrInsert(
                ['tenant_id' => $t, 'nama_kelas' => $k['nama_kelas']],
                array_merge($k, ['tenant_id' => $t, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
        $kelasIds = DB::table('kelas')->where('tenant_id', $t)->pluck('id')->all();

        // 6. Santri
        $santriData = [
            ['nis' => '2024001', 'nama' => 'Ahmad Rizki Pratama', 'jk' => 'L'],
            ['nis' => '2024002', 'nama' => 'Muhammad Faisal', 'jk' => 'L'],
            ['nis' => '2024003', 'nama' => 'Aisyah Putri Rahayu', 'jk' => 'P'],
            ['nis' => '2024004', 'nama' => 'Fatimah Az-Zahra', 'jk' => 'P'],
            ['nis' => '2024005', 'nama' => 'Abdullah Hakim', 'jk' => 'L'],
            ['nis' => '2023001', 'nama' => 'Umar Faruq', 'jk' => 'L'],
            ['nis' => '2023002', 'nama' => 'Khadijah Nur Aini', 'jk' => 'P'],
            ['nis' => '2023003', 'nama' => 'Bilal Ibrahim', 'jk' => 'L'],
            ['nis' => '2023004', 'nama' => 'Zainab Salsabila', 'jk' => 'P'],
            ['nis' => '2023005', 'nama' => 'Hasan Al-Farisi', 'jk' => 'L'],
            ['nis' => '2022001', 'nama' => 'Ali Murtadha', 'jk' => 'L'],
            ['nis' => '2022002', 'nama' => 'Ruqayah Azzahra', 'jk' => 'P'],
            ['nis' => '2024006', 'nama' => 'Salman Al-Farisi', 'jk' => 'L'],
            ['nis' => '2024007', 'nama' => 'Hafshah Nurul Iman', 'jk' => 'P'],
        ];
        $kota = ['Sumedang', 'Bandung', 'Garut', 'Tasikmalaya', 'Cirebon'];
        foreach ($santriData as $i => $s) {
            DB::table('santri')->updateOrInsert(
                ['tenant_id' => $t, 'nis' => $s['nis']],
                [
                    'nama' => $s['nama'], 'jenis_kelamin' => $s['jk'], 'status' => 'aktif',
                    'tahun_angkatan' => (int) ('20'.substr($s['nis'], 2, 2)),
                    'tanggal_lahir' => '2011-0'.(($i % 8) + 1).'-1'.(($i % 8) + 1),
                    'tempat_lahir' => $kota[$i % 5],
                    'nama_ayah' => 'Bapak '.explode(' ', $s['nama'])[0],
                    'nama_ibu' => 'Ibu '.explode(' ', $s['nama'])[0],
                    'kelas_id' => $kelasIds[$i % count($kelasIds)],
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }
        $santriIds = DB::table('santri')->where('tenant_id', $t)->where('status', 'aktif')->pluck('id');

        // 6b. Demo PWA Wali (W2/W3) — user wali + akses anak + metode bayar + saldo tabungan.
        $waliUser = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'wali@demo.pesantren.online'],
            ['name' => 'Bapak Ahmad (Wali)', 'password' => Hash::make('demo1234'), 'tenant_id' => $t]
        );
        if (Role::where('name', 'wali')->exists()) {
            $waliUser->syncRoles(['wali']);
        }

        $santriDemo = DB::table('santri')->where('tenant_id', $t)->where('nis', '2024001')->first();
        if ($santriDemo) {
            DB::table('access_wali')->updateOrInsert(
                ['tenant_id' => $t, 'wali_id' => $waliUser->id, 'santri_id' => $santriDemo->id],
                ['relationship_type' => 'orang_tua', 'is_primary' => true, 'created_at' => $now, 'updated_at' => $now]
            );
            DB::table('santri')->where('id', $santriDemo->id)->update(['wali_user_id' => $waliUser->id]);

            // Tabungan + saldo awal untuk santri demo (agar bayar-dari-saldo bisa langsung didemokan).
            DB::table('tabungan')->updateOrInsert(
                ['tenant_id' => $t, 'santri_id' => $santriDemo->id],
                ['saldo' => 750000, 'is_frozen' => false, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Metode pembayaran tenant (minimal 2: bank + qris).
        foreach ([
            ['tipe' => 'bank', 'nama' => 'Bank Syariah Indonesia', 'nomor' => '7012345678', 'atas_nama' => 'Yayasan Pesantren Demo', 'instruksi' => 'Transfer lalu unggah bukti.', 'urutan' => 1],
            ['tipe' => 'qris', 'nama' => 'QRIS Pesantren', 'nomor' => null, 'atas_nama' => 'Pesantren Demo', 'instruksi' => 'Scan QR di aplikasi e-wallet.', 'urutan' => 2],
        ] as $m) {
            DB::table('metode_pembayaran')->updateOrInsert(
                ['tenant_id' => $t, 'nama' => $m['nama']],
                array_merge($m, ['tenant_id' => $t, 'gambar' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }

        // 7. Tagihan SPP Mei (10 lunas, sisa belum) + Juni (belum)
        foreach ($santriIds as $i => $sid) {
            $statusMei = $i < 10 ? 'lunas' : 'belum_bayar';
            DB::table('tagihan')->updateOrInsert(
                ['tenant_id' => $t, 'santri_id' => $sid, 'jenis_tagihan' => 'spp', 'bulan_masehi' => '2026-05'],
                ['keterangan' => 'SPP Mei 2026', 'nominal' => 500000, 'diskon' => 0, 'nominal_akhir' => 500000, 'jatuh_tempo' => '2026-05-10', 'status' => $statusMei, 'periode_akademik' => '2025/2026 Genap', 'created_at' => $now, 'updated_at' => $now]
            );
            DB::table('tagihan')->updateOrInsert(
                ['tenant_id' => $t, 'santri_id' => $sid, 'jenis_tagihan' => 'spp', 'bulan_masehi' => '2026-06'],
                ['keterangan' => 'SPP Juni 2026', 'nominal' => 500000, 'diskon' => 0, 'nominal_akhir' => 500000, 'jatuh_tempo' => '2026-06-10', 'status' => 'belum_bayar', 'periode_akademik' => '2025/2026 Genap', 'created_at' => $now, 'updated_at' => $now]
            );
        }
        // 8. Pembayaran untuk tagihan Mei lunas
        $metode = ['tunai', 'transfer', 'qris'];
        foreach (DB::table('tagihan')->where('tenant_id', $t)->where('bulan_masehi', '2026-05')->where('status', 'lunas')->get() as $i => $tag) {
            DB::table('pembayaran')->updateOrInsert(
                ['tenant_id' => $t, 'tagihan_id' => $tag->id],
                ['santri_id' => $tag->santri_id, 'nominal' => 500000, 'metode' => $metode[$i % 3], 'tanggal_pembayaran' => '2026-05-'.str_pad((string) (($i % 14) + 5), 2, '0', STR_PAD_LEFT), 'is_verified' => true, 'verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // 9. Pelajaran
        $mapel = [
            ['nama' => 'Fiqih', 'kode' => 'FQH', 'sks' => 3],
            ['nama' => 'Aqidah Akhlak', 'kode' => 'AQD', 'sks' => 2],
            ['nama' => 'Al-Quran Hadits', 'kode' => 'QHD', 'sks' => 3],
            ['nama' => 'Bahasa Arab', 'kode' => 'BAR', 'sks' => 3],
            ['nama' => 'Nahwu Shorof', 'kode' => 'NHS', 'sks' => 2],
            ['nama' => 'Matematika', 'kode' => 'MTK', 'sks' => 3],
            ['nama' => 'Bahasa Indonesia', 'kode' => 'BIN', 'sks' => 2],
            ['nama' => 'IPA', 'kode' => 'IPA', 'sks' => 3],
        ];
        foreach ($mapel as $m) {
            DB::table('pelajaran')->updateOrInsert(
                ['tenant_id' => $t, 'kode' => $m['kode']],
                array_merge($m, ['tenant_id' => $t, 'created_at' => $now, 'updated_at' => $now])
            );
        }

        // 10. Blok asrama
        foreach ([['nama_blok' => 'Baitul Ilmi (Putra)', 'lokasi' => 'Gedung A', 'jumlah_kamar' => 10], ['nama_blok' => 'Baitul Hikmah (Putri)', 'lokasi' => 'Gedung B', 'jumlah_kamar' => 8]] as $b) {
            DB::table('blok_asrama')->updateOrInsert(
                ['tenant_id' => $t, 'nama_blok' => $b['nama_blok']],
                array_merge($b, ['tenant_id' => $t, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }

        // 11. Pendaftar PPDB
        $pendaftar = [
            ['nama' => 'Yusuf Abdurrahman', 'jk' => 'L', 'prog' => 'Tahfidz', 'status' => 'pending'],
            ['nama' => 'Maryam Salsabila', 'jk' => 'P', 'prog' => 'Reguler', 'status' => 'diterima'],
            ['nama' => 'Ibrahim Khalil', 'jk' => 'L', 'prog' => 'Kitab Kuning', 'status' => 'pending'],
            ['nama' => 'Aminah Zahratul', 'jk' => 'P', 'prog' => 'Tahfidz', 'status' => 'diterima'],
        ];
        foreach ($pendaftar as $i => $p) {
            DB::table('calon_santri')->updateOrInsert(
                ['tenant_id' => $t, 'nomor_pendaftaran' => sprintf('PPDB-2026-%04d', $i + 1)],
                [
                    'tracking_token' => Str::random(40), 'tracking_token_generated_at' => $now,
                    'nama' => $p['nama'], 'tanggal_lahir' => '2012-0'.($i + 2).'-1'.($i + 1),
                    'tempat_lahir' => $kota[$i % 5], 'jenis_kelamin' => $p['jk'],
                    'nama_ayah' => 'Bapak '.explode(' ', $p['nama'])[0], 'nama_ibu' => 'Ibu '.explode(' ', $p['nama'])[0],
                    'no_hp_wali' => '0812'.rand(10000000, 99999999), 'email_wali' => strtolower(explode(' ', $p['nama'])[0]).'@gmail.com',
                    'alamat' => 'Jl. Contoh No. '.($i + 1).', Sumedang', 'asal_sekolah' => 'SD/MI Contoh',
                    'program_diminati' => $p['prog'], 'status_pendaftaran' => $p['status'],
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }
    }
}
