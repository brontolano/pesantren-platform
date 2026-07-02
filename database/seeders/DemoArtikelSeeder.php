<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\CMS\Models\Article;

/**
 * Konten artikel DEMO (etalase) — dijalankan pada tenant demo agar Beranda &
 * aplikasi native punya isi. Idempoten (updateOrCreate by tenant_id+slug).
 * Dipanggil via `php artisan demo:artikel` (DemoArtikelCommand) di tenant demo.
 */
class DemoArtikelSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->getTenantKey();

        if ($tenantId === null) {
            return;
        }

        $base = Carbon::now();

        $artikel = [
            [
                'slug' => 'penerimaan-santri-baru-dibuka',
                'category' => 'announcement',
                'title' => 'Penerimaan Santri Baru Tahun Ajaran Ini Resmi Dibuka',
                'excerpt' => 'Pendaftaran santri baru kini dibuka melalui jalur reguler dan tahfidz. Kuota terbatas.',
                'content' => "Dengan memohon ridha Allah SWT, pesantren membuka pendaftaran santri baru untuk tahun ajaran ini.\n\nPendaftaran dapat dilakukan secara daring melalui menu PPDB Online. Tersedia jalur reguler dan jalur tahfidz dengan kuota terbatas. Seleksi meliputi wawancara, tes baca Al-Qur'an, dan tes dasar akademik.\n\nSilakan siapkan berkas: akta kelahiran, kartu keluarga, dan pas foto. Untuk informasi lebih lanjut, hubungi panitia melalui kontak yang tersedia.",
                'days_ago' => 1,
            ],
            [
                'slug' => 'wisuda-tahfidz-30-juz',
                'category' => 'achievement',
                'title' => 'Alhamdulillah, 12 Santri Wisuda Tahfidz 30 Juz',
                'excerpt' => 'Sebanyak 12 santri menyelesaikan hafalan 30 juz dan diwisuda dalam haflah akhirussanah.',
                'content' => "Segala puji bagi Allah, pada haflah akhirussanah tahun ini sebanyak 12 santri resmi diwisuda sebagai hafizh/hafizhah 30 juz.\n\nProsesi wisuda dihadiri oleh para wali santri, asatidz, dan tamu undangan. Para wisudawan menunjukkan kelancaran hafalan melalui sambung ayat di hadapan dewan penguji.\n\nSemoga ilmu yang diraih menjadi cahaya dan keberkahan, serta istiqamah menjaga hafalannya.",
                'days_ago' => 5,
            ],
            [
                'slug' => 'kegiatan-bakti-sosial-ramadhan',
                'category' => 'article',
                'title' => 'Santri Gelar Bakti Sosial dan Santunan Anak Yatim',
                'excerpt' => 'Dalam rangka mengasah kepedulian, santri mengadakan bakti sosial ke desa sekitar.',
                'content' => "Sebagai bagian dari pendidikan karakter, santri menyelenggarakan kegiatan bakti sosial di lingkungan sekitar pesantren.\n\nKegiatan meliputi santunan anak yatim, pembagian sembako, serta kerja bakti membersihkan masjid dan fasilitas umum. Para santri turun langsung berinteraksi dengan masyarakat.\n\nKegiatan ini diharapkan menumbuhkan rasa empati, tanggung jawab sosial, dan kecintaan untuk berbagi.",
                'days_ago' => 9,
            ],
            [
                'slug' => 'juara-musabaqah-tilawatil-quran',
                'category' => 'achievement',
                'title' => 'Santri Raih Juara MTQ Tingkat Kabupaten',
                'excerpt' => 'Delegasi pesantren meraih juara pada cabang tilawah dan tahfizh MTQ tingkat kabupaten.',
                'content' => "Kabar membanggakan datang dari ajang Musabaqah Tilawatil Qur'an (MTQ) tingkat kabupaten.\n\nDelegasi pesantren berhasil meraih juara pada cabang tilawah dewasa dan tahfizh 10 juz. Prestasi ini buah dari pembinaan rutin tahsin dan tahfizh setiap hari.\n\nSelamat kepada para santri berprestasi. Semoga menjadi motivasi bagi seluruh santri untuk terus berkarya bersama Al-Qur'an.",
                'days_ago' => 14,
            ],
            [
                'slug' => 'pembangunan-asrama-baru',
                'category' => 'announcement',
                'title' => 'Pembangunan Asrama Baru Memasuki Tahap Akhir',
                'excerpt' => 'Pembangunan gedung asrama baru ditargetkan rampung sebelum tahun ajaran dimulai.',
                'content' => "Untuk menampung pertumbuhan jumlah santri, pesantren membangun gedung asrama baru berkapasitas lebih luas.\n\nSaat ini pembangunan telah memasuki tahap akhir berupa finishing dan pemasangan fasilitas. Asrama dilengkapi ruang belajar, tempat wudhu yang memadai, dan ventilasi yang baik.\n\nKami mengucapkan terima kasih atas dukungan dan doa para muhsinin. Semoga menjadi amal jariyah yang mengalir pahalanya.",
                'days_ago' => 20,
            ],
            [
                'slug' => 'jadwal-kajian-rutin-pekanan',
                'category' => 'article',
                'title' => 'Jadwal Kajian Rutin Pekanan Terbuka untuk Umum',
                'excerpt' => 'Kajian kitab kuning dan tausiyah pekanan terbuka untuk jemaah dan masyarakat umum.',
                'content' => "Pesantren membuka kajian rutin pekanan yang terbuka untuk jemaah dan masyarakat umum.\n\nKajian meliputi pembacaan kitab kuning, fiqih ibadah, serta tausiyah penyejuk hati. Dilaksanakan ba'da Maghrib di masjid pesantren.\n\nMari hadir dan ajak keluarga untuk menambah ilmu dan mempererat ukhuwah. Jadwal lengkap dapat dilihat pada menu Kegiatan.",
                'days_ago' => 26,
            ],
        ];

        foreach ($artikel as $a) {
            Article::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $a['slug']],
                [
                    'title' => $a['title'],
                    'category' => $a['category'],
                    'excerpt' => $a['excerpt'],
                    'content' => $a['content'],
                    'is_published' => true,
                    'published_at' => $base->copy()->subDays($a['days_ago']),
                    'meta_title' => $a['title'],
                    'meta_description' => $a['excerpt'],
                ],
            );
        }
    }
}
