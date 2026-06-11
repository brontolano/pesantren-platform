<?php

namespace Modules\Akademik\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Akademik\Models\KalenderAkademik;

/**
 * WP-02 — Libur nasional Indonesia 2026 (statis). Tanggal berbasis Hijriyah
 * adalah PERKIRAAN (mengikuti kalender ummul qura / SKB sementara) — tenant
 * bebas mengedit lewat resource Kalender Akademik.
 *
 * Idempoten: firstOrCreate kunci (tenant via BelongsToTenant) +
 * tanggal_mulai + keterangan — aman dipanggil ulang tenant:reprovision,
 * dan TIDAK menimpa entri yang sudah diedit tenant.
 */
class KalenderLiburNasionalSeeder extends Seeder
{
    /** @return array<int, array{mulai:string, selesai:string, keterangan:string}> */
    public static function liburNasional2026(): array
    {
        return [
            ['mulai' => '2026-01-01', 'selesai' => '2026-01-01', 'keterangan' => 'Tahun Baru 2026 Masehi'],
            ['mulai' => '2026-01-16', 'selesai' => '2026-01-16', 'keterangan' => 'Isra Mikraj Nabi Muhammad SAW (perkiraan)'],
            ['mulai' => '2026-02-17', 'selesai' => '2026-02-17', 'keterangan' => 'Tahun Baru Imlek 2577 Kongzili'],
            ['mulai' => '2026-03-19', 'selesai' => '2026-03-19', 'keterangan' => 'Hari Suci Nyepi (Tahun Baru Saka 1948)'],
            ['mulai' => '2026-03-20', 'selesai' => '2026-03-21', 'keterangan' => 'Hari Raya Idul Fitri 1447 H (perkiraan)'],
            ['mulai' => '2026-04-03', 'selesai' => '2026-04-03', 'keterangan' => 'Wafat Isa Almasih (Jumat Agung)'],
            ['mulai' => '2026-05-01', 'selesai' => '2026-05-01', 'keterangan' => 'Hari Buruh Internasional'],
            ['mulai' => '2026-05-14', 'selesai' => '2026-05-14', 'keterangan' => 'Kenaikan Isa Almasih'],
            ['mulai' => '2026-05-27', 'selesai' => '2026-05-27', 'keterangan' => 'Hari Raya Idul Adha 1447 H (perkiraan)'],
            ['mulai' => '2026-05-31', 'selesai' => '2026-05-31', 'keterangan' => 'Hari Raya Waisak 2570 BE (perkiraan)'],
            ['mulai' => '2026-06-01', 'selesai' => '2026-06-01', 'keterangan' => 'Hari Lahir Pancasila'],
            ['mulai' => '2026-06-16', 'selesai' => '2026-06-16', 'keterangan' => 'Tahun Baru Islam 1448 H (perkiraan)'],
            ['mulai' => '2026-08-17', 'selesai' => '2026-08-17', 'keterangan' => 'Hari Kemerdekaan RI ke-81'],
            ['mulai' => '2026-08-25', 'selesai' => '2026-08-25', 'keterangan' => 'Maulid Nabi Muhammad SAW (perkiraan)'],
            ['mulai' => '2026-12-25', 'selesai' => '2026-12-25', 'keterangan' => 'Hari Raya Natal'],
        ];
    }

    public function run(): void
    {
        foreach (self::liburNasional2026() as $libur) {
            // Kunci idempoten = keterangan SAJA (sudah unik per tahun di
            // daftar ini). JANGAN ikutkan tanggal_mulai: tanggal Hijriyah
            // adalah perkiraan — tenant yang MENGGESER tanggal_mulai tidak
            // boleh memunculkan entri duplikat saat reprovision/reseed.
            // Seeder tahun berikutnya WAJIB menyertakan tahun pada keterangan.
            KalenderAkademik::query()->firstOrCreate(
                [
                    'keterangan' => $libur['keterangan'],
                ],
                [
                    'tanggal_mulai' => $libur['mulai'],
                    'tanggal_selesai' => $libur['selesai'],
                    'jenis' => 'libur_nasional',
                    'lembaga_id' => null, // berlaku semua lembaga
                ],
            );
        }
    }
}
