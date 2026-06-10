<?php

namespace Modules\AI\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Models\AiTemplateRpp;

/**
 * T-A041 — seed SATU template RPP default GENERIK NETRAL (tabel TENANT) —
 * IDEMPOTEN. Dipanggil AiSettingProvisioner saat provisioning tenant
 * (pola termurah — ikut tenant:reprovision tanpa langkah baru) dan bisa
 * dijalankan manual sebagai seeder di konteks tenant.
 *
 * ADAPTASI SADAR (Aturan Emas #11 + #8): TANPA taksonomi regulasi
 * (KMA 939/2024, KMA 1495/2025, dst.) — regulasi adalah konten yang harus
 * ditinjau manusia. Seksi di bawah hanyalah field UMUM dokumen RPP;
 * Wakakurikulum bebas mengubah/menghapus/menambah seksi per jenis lembaga
 * lewat halaman Generator RPP.
 *
 * Idempoten dengan menghormati kepemilikan tenant: bila tenant SUDAH punya
 * template apa pun (termasuk pernah menghapus default), seeder tidak
 * menambah/menimpa apa-apa.
 */
class AiTemplateRppSeeder extends Seeder
{
    public function run(): void
    {
        self::seedDefault();
    }

    public static function seedDefault(): void
    {
        if (! Schema::hasTable('ai_template_rpp') || AiTemplateRpp::query()->exists()) {
            return;
        }

        AiTemplateRpp::query()->create([
            'nama' => 'Template RPP Umum (bawaan)',
            'jenis_lembaga' => null, // generik — tenant menentukan istilahnya sendiri
            'aktif' => true,
            'dibuat_oleh' => null,
            'seksi' => [
                [
                    'kunci' => 'identitas',
                    'judul' => 'Identitas',
                    // Kata "salin" menandai seksi TANPA LLM (RppGenerator):
                    // identitas datang dari isian guru, bukan dikarang AI.
                    'petunjuk' => 'Salin data identitas dari isian guru: nama lembaga, mapel/kitab, kelas/jenjang, alokasi waktu, dan nama penyusun. Bagian ini tidak diisi oleh asisten.',
                    'wajib' => true,
                ],
                [
                    'kunci' => 'capaian',
                    'judul' => 'Capaian Pembelajaran',
                    'petunjuk' => 'Rumuskan capaian pembelajaran umum berdasarkan materi pada konteks, dalam kalimat utuh tanpa menyalin mentah.',
                    'wajib' => true,
                ],
                [
                    'kunci' => 'tujuan',
                    'judul' => 'Tujuan Pembelajaran',
                    'petunjuk' => 'Turunkan dua sampai empat tujuan pembelajaran yang spesifik dan terukur dari materi.',
                    'wajib' => true,
                ],
                [
                    'kunci' => 'materi',
                    'judul' => 'Materi Pembelajaran',
                    'petunjuk' => 'Ringkas pokok-pokok materi yang akan diajarkan secara berurutan.',
                    'wajib' => true,
                ],
                [
                    'kunci' => 'metode',
                    'judul' => 'Metode Pembelajaran',
                    'petunjuk' => 'Usulkan metode atau strategi pembelajaran yang sesuai dengan materi dan tingkat kelas.',
                    'wajib' => false,
                ],
                [
                    'kunci' => 'langkah',
                    'judul' => 'Langkah Kegiatan Pembelajaran',
                    'petunjuk' => 'Susun langkah kegiatan: pendahuluan, inti, dan penutup, beserta perkiraan alokasi waktu tiap bagian.',
                    'wajib' => true,
                ],
                [
                    'kunci' => 'asesmen',
                    'judul' => 'Asesmen / Penilaian',
                    'petunjuk' => 'Usulkan bentuk asesmen untuk mengukur ketercapaian tujuan, termasuk contoh instrumen singkat.',
                    'wajib' => false,
                ],
                [
                    'kunci' => 'sumber',
                    'judul' => 'Sumber & Media Belajar',
                    'petunjuk' => 'Sebutkan sumber belajar dan media yang dipakai berdasarkan materi pada konteks dan isian guru.',
                    'wajib' => false,
                ],
            ],
        ]);
    }
}
