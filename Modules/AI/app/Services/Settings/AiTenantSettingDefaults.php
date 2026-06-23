<?php

namespace Modules\AI\Services\Settings;

/**
 * T-A010 — payload default PALING KONSERVATIF untuk ai_tenant_setting
 * (Blueprint §8 butir 2). Tenant baru lahir dengan kebijakan teraman:
 * AI hanya edukatif, santri tanpa akses, data minimum, tanpa riset.
 *
 * Struktur = katalog Wizard PRD §6.3 (14 kelompok field). Aturan Emas #11:
 * SELURUH nilai di sini adalah DATA awal — perilaku per pesantren diubah
 * lewat Wizard, bukan lewat kode.
 */
class AiTenantSettingDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function konservatif(): array
    {
        return [
            // Manhaj/afiliasi — preset Guardrails dimuat dari berkas seed
            // tinjauan ustadz (GATE-3), bukan dari kode.
            'preset_manhaj' => 'aswaja_nu',

            // Paling ketat: hanya materi edukatif; semua tanya hukum dialihkan.
            'level_fatwa' => 'edukatif_only',

            // ============================ PENTING ============================
            // whitelist_kitab & blacklist_topik default SENGAJA KOSONG.
            // Isi keagamaan (daftar kitab rujukan per manhaj, daftar topik
            // terlarang: nikah/talak/waris, takfir, politik praktis, aqidah
            // sensitif, dst.) HANYA boleh masuk dari berkas seed yang sudah
            // DITINJAU USTADZ (GATE-3 PRD §12.2). DILARANG KERAS dikarang
            // oleh pengembang/AI — struktur field disediakan, isinya tidak.
            // Konsekuensi aman: whitelist kosong → retrieval keagamaan tidak
            // menemukan rujukan → jawaban "silakan tanya ustadz" (PRD §6.3).
            // =================================================================
            'whitelist_kitab' => [],
            'blacklist_topik' => [],

            // Tujuan eskalasi "Tanya Ustadz" — diisi admin lewat Wizard.
            'eskalasi_tujuan' => [
                'penerima' => [],      // daftar user_id ustadz/admin
                'kanal' => 'panel',    // panel|wa
                'sla_jam' => 24,
            ],

            // Matriks fitur per peran — fitur santri default OFF (PRD §6.3).
            // T-A042: ikhtisar_kitab (asatidz/admin) & generator_kultum
            // (asatidz) — KEDUANYA default OFF; khusus kultum OPT-IN
            // disengaja (rekomendasi Q8 PRD, belum dikonfirmasi owner).
            'fitur_per_peran' => [
                'asatidz' => [
                    'generator_soal' => false,
                    'generator_rpp' => false,
                    'ikhtisar_kitab' => false,
                    'generator_kultum' => false,
                    'chatbot' => false,
                ],
                'admin' => ['chatbot' => false, 'dokumen_tu' => false, 'ikhtisar_kitab' => false],
                'bendahara' => ['ringkasan_keuangan' => false],
                'wali' => ['chatbot' => false],
                'santri' => ['tutor' => false], // WAJIB false (konservatif)
            ],

            // Kebijakan perangkat santri: tanpa akses sama sekali.
            'mode_akses_santri' => 'tanpa_akses',

            // Scope data yang boleh diakses AI — minimum (tagihan+jadwal saja).
            'scope_data' => [
                'tagihan' => true,
                'jadwal' => true,
                'nilai' => false,
                'kehadiran' => false,
                'kesehatan' => false,
                'keuangan' => false,
            ],

            'bahasa' => 'id',
            'gaya' => 'formal_santun',

            // gap-135 — mode uji chatbot WA: true = balas template statis
            // (tanpa LLM), false = pipeline intent/RAG aktif. Default true
            // = perilaku teraman & sama dengan env AI_WA_MODE_UJI bawaan.
            // Field NON-sensitif: perubahan langsung aktif tanpa approval kyai.
            'mode_uji_wa' => true,

            // 24 jam: di luar jam tidak pernah terjadi; pesan tetap tersedia.
            'jam_layanan' => ['mulai' => '00:00', 'selesai' => '23:59'],
            'pesan_di_luar_jam' => 'Mohon maaf, layanan asisten sedang di luar jam operasional. Pertanyaan Anda kami catat dan akan ditindaklanjuti pada jam layanan. Terima kasih atas pengertiannya.',

            // Anggaran null = ikut budget central (T-A002); alert 80%.
            'anggaran_kredit_bulanan' => null,
            'ambang_alert_persen' => 80,

            // Retensi percakapan (UU PDP): umum 90 hari, santri 30 hari.
            'retensi_hari' => 90,
            'retensi_hari_santri' => 30,

            // Prasyarat keras fitur santri (Fase C) — default belum disetel.
            'consent_ortu' => false,

            // Telemetri riset produk — default TIDAK ikut.
            'optin_riset' => false,
        ];
    }
}
