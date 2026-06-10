<?php

namespace Modules\AI\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AI\Models\AiPolicy;

/**
 * T-A020 — seeder STRUKTURAL KOSONG kebijakan guardrails (jalan di DB tenant).
 *
 * ====================== PERINGATAN ATURAN EMAS #8 ======================
 * Seeder ini HANYA membuat wadah policy "Kebijakan Pesantren" (sumber
 * tenant) TANPA SATU PUN rule keagamaan. Preset blacklist topik, daftar
 * topik terlarang per manhaj, dan rule fikih lainnya HANYA boleh masuk
 * dari berkas seed yang sudah DITINJAU & DISETUJUI USTADZ (GATE-3,
 * PRD §12.2 — berkas itu BELUM ADA). DILARANG KERAS menambah konten
 * keagamaan di sini, oleh pengembang maupun AI.
 * =======================================================================
 *
 * Rule blacklist milik tenant sendiri masuk lewat mirror setting
 * (PolicyEngine::syncDariSetting), bukan lewat seeder ini.
 */
class AiPolicySeeder extends Seeder
{
    public function run(): void
    {
        AiPolicy::query()->firstOrCreate(
            ['nama' => AiPolicy::NAMA_KEBIJAKAN_PESANTREN, 'sumber' => AiPolicy::SUMBER_TENANT],
            ['aktif' => true],
        );
    }
}
