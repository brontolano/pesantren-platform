<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Mengaitkan persona auto-login demo (guru.demo / kasir.demo / pimpinan.demo —
 * lihat DemoAutoLoginController) ke DATA agar layar PWA tidak kosong saat demo:
 *
 *  - GURU  : mengampu 3 pelajaran + jadwal SEMUA hari (huruf kecil, cocok
 *            hariIni()) + 2 halaqah berisi santri + wali kelas.
 *  - PIMPINAN: 2 ApprovalRequest (approver_role 'kepala') menunjuk Belanja
 *            yang ada → inbox approval terisi (nominal/pemohon tampil).
 *  - KEHADIRAN: presensi_santri hari ini → persen kehadiran dasbor pimpinan.
 *
 * WAJIB dijalankan dalam konteks tenancy demo (koneksi tenant aktif). Idempoten.
 *   Tenant::find('demo')->run(fn () => (new DemoPersonaSeeder())->run());
 */
class DemoPersonaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $guru = $this->persona('guru.demo@demo.pesantren.online', 'Ustadz Demo', 'guru');
        $kasir = $this->persona('kasir.demo@demo.pesantren.online', 'Kasir Demo', 'keuangan');
        $this->persona('pimpinan.demo@demo.pesantren.online', 'Pimpinan Demo', 'kepala');

        if ($guru && Schema::hasTable('pelajaran')) {
            $this->wireGuru($guru->id, $now);
        }
        if ($kasir && Schema::hasTable('approval_request')) {
            $this->wirePimpinanApproval($kasir->id, $now);
        }
        if (Schema::hasTable('presensi_santri')) {
            $this->wireKehadiran($guru?->id, $now);
        }
    }

    private function persona(string $email, string $name, string $role): ?User
    {
        $user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(Str::random(40)), 'tenant_id' => tenant('id') ?? 'demo'],
        );
        if (Role::where('name', $role)->exists() && ! $user->hasRole($role)) {
            $user->syncRoles([$role]);
        }

        return $user;
    }

    private function wireGuru(int $guruId, $now): void
    {
        // 3 pelajaran pertama diampu guru.demo.
        $pelajaranIds = DB::table('pelajaran')->orderBy('id')->limit(3)->pluck('id')->all();
        DB::table('pelajaran')->whereIn('id', $pelajaranIds)->update(['guru_pengampu_id' => $guruId, 'updated_at' => $now]);

        // Jadwal SEMUA hari (huruf kecil — cocok ResolvesPortalGuruContext::hariIni()).
        $hari = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
        $jam = [['07:00', '08:30', 'Aula 1'], ['08:30', '10:00', 'Kelas 2A'], ['10:15', '11:45', 'Kelas 3A']];
        $kelasIds = DB::table('kelas')->orderBy('id')->limit(3)->pluck('id')->all();
        $periode = optional(DB::table('periode_akademik')->where('is_active', true)->first())->id ?? 1;
        foreach (array_values($pelajaranIds) as $i => $pid) {
            $kelasId = $kelasIds[$i] ?? ($kelasIds[0] ?? null);
            if (! $kelasId) {
                continue;
            }
            foreach ($hari as $h) {
                DB::table('jadwal_pelajaran')->updateOrInsert(
                    ['tenant_id' => tenant('id') ?? 'demo', 'kelas_id' => $kelasId, 'pelajaran_id' => $pid, 'hari' => $h, 'periode_akademik_id' => $periode],
                    ['jam_mulai' => $jam[$i][0], 'jam_selesai' => $jam[$i][1], 'ruangan' => $jam[$i][2], 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        // 2 halaqah binaan + anggota santri.
        if (Schema::hasTable('halaqah') && Schema::hasTable('halaqah_santri')) {
            $santriIds = DB::table('santri')->orderBy('id')->limit(12)->pluck('id')->all();
            $defs = [
                ['Halaqah Tahfidz Pagi', 'tahfidz', array_slice($santriIds, 0, 6)],
                ['Halaqah Kitab Kuning', 'kitab', array_slice($santriIds, 6, 6)],
            ];
            foreach ($defs as [$nama, $jenis, $anggota]) {
                DB::table('halaqah')->updateOrInsert(
                    ['tenant_id' => tenant('id') ?? 'demo', 'nama' => $nama],
                    ['musyrif_id' => $guruId, 'jenis' => $jenis, 'aktif' => true, 'updated_at' => $now, 'created_at' => $now],
                );
                $hid = DB::table('halaqah')->where('tenant_id', tenant('id') ?? 'demo')->where('nama', $nama)->value('id');
                foreach ($anggota as $sid) {
                    DB::table('halaqah_santri')->updateOrInsert(
                        ['tenant_id' => tenant('id') ?? 'demo', 'halaqah_id' => $hid, 'santri_id' => $sid],
                        ['joined_at' => $now, 'left_at' => null, 'updated_at' => $now, 'created_at' => $now],
                    );
                }
            }
        }

        // Wali kelas (kelas pertama).
        if (Schema::hasColumn('kelas', 'wali_kelas_id') && ! empty($kelasIds)) {
            DB::table('kelas')->where('id', $kelasIds[0])->update(['wali_kelas_id' => $guruId, 'updated_at' => $now]);
        }
    }

    private function wirePimpinanApproval(int $pemohonId, $now): void
    {
        // Tunjuk hingga 2 Belanja yang ada → approval untuk role 'kepala'.
        $belanjaIds = Schema::hasTable('belanja')
            ? DB::table('belanja')->orderBy('id')->limit(2)->pluck('id')->all()
            : [];
        foreach ($belanjaIds as $bid) {
            DB::table('approval_request')->updateOrInsert(
                ['tenant_id' => tenant('id') ?? 'demo', 'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja', 'approvable_id' => $bid, 'approver_role' => 'kepala'],
                ['status' => 'menunggu', 'current_step' => 1, 'diminta_oleh' => $pemohonId, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    private function wireKehadiran(?int $pencatatId, $now): void
    {
        $today = $now->toDateString();
        $i = 0;
        foreach (DB::table('santri')->get(['id', 'kelas_id']) as $s) {
            $i++;
            $status = 'hadir';
            if ($i % 10 === 0) {
                $status = 'izin';
            } elseif ($i % 7 === 0) {
                $status = 'sakit';
            }
            DB::table('presensi_santri')->updateOrInsert(
                ['tenant_id' => tenant('id') ?? 'demo', 'santri_id' => $s->id, 'tanggal' => $today],
                ['kelas_id' => $s->kelas_id, 'status' => $status, 'dicatat_oleh' => $pencatatId, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }
}
