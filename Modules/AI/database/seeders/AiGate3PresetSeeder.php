<?php

namespace Modules\AI\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiPolicy;
use Modules\AI\Models\AiPolicyRule;

/**
 * gap-83 "Pintu rilis AI + GATE-3" — SCAFFOLD seeder preset keagamaan GATE-3.
 *
 * ====================== PERINGATAN ATURAN EMAS #8 ======================
 * Seeder ini TIDAK mengarang satu pun konten keagamaan. Ia hanya MEMUAT
 * berkas seed yang sudah DITINJAU & DISETUJUI USTADZ (GATE-3, PRD §12.2) —
 * dan HANYA bila berkas itu ada DAN ditandai 'disetujui'. Di repo saat ini
 * berkas tersebut SENGAJA BELUM ADA, jadi seeder ini = NO-OP senyap.
 * DILARANG KERAS menaruh daftar kitab / daftar topik larangan langsung di
 * kode PHP ini, oleh pengembang maupun AI.
 * =======================================================================
 *
 * ------------------------------------------------------------------------
 * APA INI?
 * ------------------------------------------------------------------------
 * Pasangan struktural dari AiPolicySeeder (yang hanya membuat WADAH policy
 * tenant kosong). Seeder ini menyiapkan jalur masuk preset PLATFORM untuk:
 *   - blacklist_topik  → AiPolicyRule (tipe blacklist_topik) di policy
 *                        ber-sumber 'preset' (dikonsumsi PolicyEngine apa
 *                        adanya — lihat PolicyEngine::ambilRuleBlacklist).
 *   - whitelist_kitab  → TIDAK ditulis di sini. Whitelist hidup sebagai
 *                        DATA pengaturan tenant (ai_tenant_setting) dan
 *                        diatur per pesantren lewat Wizard "Pengaturan AI"
 *                        melalui alur approval kyai (TenantSettingService).
 *                        Seeder hanya MENCATAT katalog whitelist preset ke
 *                        log agar terlihat saat diaktifkan — bukan memaksa
 *                        ke setiap tenant (keputusan owner 2026-06-11:
 *                        kitab rujukan dipilih per pesantren).
 *
 * ------------------------------------------------------------------------
 * KONTEN APA YANG HARUS USTADZ ISI, DAN DI MANA?
 * ------------------------------------------------------------------------
 * Berkas template MENTAH (untuk diisi Dewan Ustadz) sudah disiapkan di luar
 * repo aplikasi:
 *
 *   _PLAN/05-AI/seed-gate3/
 *     ├─ PANDUAN-PENGISIAN-SEED-USTADZ.md   (cara mengisi — baca dulu)
 *     ├─ blacklist-topik-default.json       (daftar topik AI dilarang jawab)
 *     ├─ preset-manhaj-aswaja-nu.json       (whitelist kitab rujukan)
 *     ├─ template-pengalihan.json           (kalimat penolakan/pengalihan)
 *     └─ kasus-eval-syariah.json            (bank kasus uji gate rilis)
 *
 * Setelah Dewan Ustadz mengisi & menyetujui (mengisi `_peninjau`,
 * `_tanggal_tinjauan`, dan mengubah `_status_berkas` → "disetujui"), berkas
 * preset DISALIN ke folder data seeder modul:
 *
 *   Modules/AI/database/seeders/data/
 *     ├─ gate3-preset-syariah.json    ← dibaca seeder INI (blacklist+whitelist)
 *     └─ kasus-eval-syariah.json      ← dibaca AiEvalCaseSeeder (kasus uji)
 *
 * Bentuk gate3-preset-syariah.json yang DITERIMA seeder ini terdokumentasi
 * di berkas contoh: data/gate3-preset-syariah.json.example (TIDAK pernah
 * dimuat — ekstensi .example diabaikan; murni rujukan struktur).
 *
 * ------------------------------------------------------------------------
 * LANGKAH PROMOTE YANG SENGAJA DITAHAN (butuh sign-off owner + ustadz)
 * ------------------------------------------------------------------------
 * 1. Dewan Ustadz isi & setujui 4 berkas di _PLAN/05-AI/seed-gate3/.
 * 2. Salin gate3-preset-syariah.json + kasus-eval-syariah.json ke
 *    Modules/AI/database/seeders/data/ (berstatus "disetujui").
 * 3. Jalankan gate eval terhadap MODEL PRODUKSI di VPS:
 *      php artisan ai:eval --suite=syariah   (ambang >= 98%)
 *      php artisan ai:eval --suite=faktual   (ambang >= 97%)
 *      php artisan ai:eval --suite=injection (ambang 100%)
 *    Hasil dilaporkan ke Dewan Ustadz untuk persetujuan akhir.
 * 4. SETELAH lulus + sign-off owner: ubah module_registry 'ai' dari
 *    'development' -> 'released' (database/seeders/ModuleRegistrySeeder.php
 *    baris katalog 'ai') dan aktifkan modul ke tenant terkait
 *    (tenant_modules) lewat Pusat Modul / Aktivasi Modul.
 *
 *    >>> LANGKAH 4 DITAHAN. Tidak dilakukan di task ini. Promote registry &
 *        aktivasi tenant HANYA boleh setelah konten ustadz masuk + eval
 *        lulus + persetujuan tertulis owner. <<<
 *
 * Idempoten & fail-safe: tanpa berkas yang disetujui, run() tidak menyentuh
 * basis data sama sekali. Jalankan manual (central/tenant) bila berkas siap:
 *   php artisan db:seed --class="Modules\\AI\\Database\\Seeders\\AiGate3PresetSeeder"
 */
class AiGate3PresetSeeder extends Seeder
{
    /** Nama policy preset platform (sumber 'preset', terpisah dari policy tenant). */
    public const NAMA_POLICY_PRESET = 'Preset Syariah (GATE-3)';

    /** Berkas preset yang sudah ditinjau ustadz — TIDAK ada di repo sampai GATE-3 tuntas. */
    private function pathPreset(): string
    {
        return __DIR__.'/data/gate3-preset-syariah.json';
    }

    public function run(): void
    {
        $path = $this->pathPreset();

        if (! is_file($path)) {
            // Keadaan SAAT INI: berkas tinjauan ustadz belum ada. No-op senyap
            // — bukan error. AI tetap aman: whitelist kosong → jawaban
            // keagamaan dialihkan ke ustadz (AiTenantSettingDefaults).
            $this->command?->warn(
                'AiGate3PresetSeeder: berkas preset GATE-3 belum ada — '
                .'tidak ada konten keagamaan yang ditanam (lihat _PLAN/05-AI/seed-gate3/).'
            );

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            Log::warning('AiGate3PresetSeeder: berkas preset GATE-3 tidak dapat dibaca (JSON rusak) — dilewati.', [
                'path' => $path,
            ]);

            return;
        }

        // Gerbang persetujuan KERAS: hanya berkas yang sudah ditinjau ustadz
        // (peninjau terisi) DAN ditandai disetujui yang boleh menanam konten.
        // Berkas template/draf tidak pernah aktif walau tersalin keliru.
        $peninjau = $data['_peninjau'] ?? null;
        $statusBerkas = (string) ($data['_status_berkas'] ?? '');

        if ($peninjau === null || $statusBerkas !== 'disetujui') {
            $this->command?->warn(
                'AiGate3PresetSeeder: berkas preset GATE-3 ditemukan tetapi BELUM disetujui ustadz '
                ."(_peninjau / _status_berkas != 'disetujui') — tidak ditanam."
            );
            Log::warning('AiGate3PresetSeeder: preset GATE-3 belum disetujui — dilewati.', [
                'path' => $path,
                '_peninjau' => $peninjau,
                '_status_berkas' => $statusBerkas,
            ]);

            return;
        }

        // ----- Berkas SUDAH disetujui ustadz: tanam blacklist_topik preset. -----
        // whitelist_kitab TIDAK ditanam di sini (lihat dokblok kelas — masuk
        // sebagai data setting tenant lewat Wizard). Hanya dicatat ke log.
        $this->tanamBlacklistPreset((array) ($data['blacklist_topik'] ?? []));
        $this->catatWhitelist((array) ($data['whitelist_kitab'] ?? []));
    }

    /**
     * Tanam rule blacklist_topik preset ke policy ber-sumber 'preset'.
     * IDEMPOTEN: policy firstOrCreate; tiap rule updateOrCreate by penanda.
     *
     * @param  list<array<string, mixed>>  $topik
     */
    private function tanamBlacklistPreset(array $topik): void
    {
        if ($topik === []) {
            return;
        }

        $policy = AiPolicy::query()->firstOrCreate(
            ['nama' => self::NAMA_POLICY_PRESET, 'sumber' => AiPolicy::SUMBER_PRESET],
            ['aktif' => true],
        );

        foreach ($topik as $i => $t) {
            // Lewati blok template (kategori placeholder / belum disetujui).
            $status = (string) ($t['_status'] ?? '');
            $kategori = (string) ($t['kategori'] ?? '');
            if ($status !== 'disetujui' || $kategori === '') {
                continue;
            }

            $kataKunci = array_values(array_filter(
                array_map(static fn ($k) => is_string($k) ? trim($k) : '', (array) ($t['kata_kunci'] ?? [])),
                static fn (string $k): bool => $k !== '',
            ));
            if ($kataKunci === []) {
                continue;
            }

            AiPolicyRule::query()->updateOrCreate(
                // Penanda unik per kategori preset (prioritas tinggi < mirror tenant).
                ['policy_id' => $policy->id, 'tipe' => AiPolicyRule::TIPE_BLACKLIST_TOPIK, 'prioritas' => 10 + (int) $i],
                [
                    'pola' => ['keyword' => $kataKunci, 'regex' => [], 'param' => ['kategori' => $kategori]],
                    // null = pakai template default config ai.guardrails.* (santun, sudah ada).
                    'respons_template' => null,
                    'aktif' => true,
                ],
            );
        }
    }

    /**
     * whitelist_kitab preset HANYA dicatat (tidak ditulis ke DB di sini —
     * masuk sebagai data setting per tenant lewat Wizard, dengan approval
     * kyai). Catatan ini membantu operator memverifikasi isi yang disetujui.
     *
     * @param  list<array<string, mixed>>  $kitab
     */
    private function catatWhitelist(array $kitab): void
    {
        $disetujui = array_values(array_filter(
            $kitab,
            static fn ($k): bool => is_array($k) && (string) ($k['_status'] ?? '') === 'disetujui',
        ));

        $this->command?->info(sprintf(
            'AiGate3PresetSeeder: whitelist preset memuat %d kitab disetujui — '
            .'TIDAK ditanam otomatis; diatur per pesantren lewat Wizard Pengaturan AI.',
            count($disetujui),
        ));
    }
}
