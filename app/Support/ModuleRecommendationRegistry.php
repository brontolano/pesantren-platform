<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Mesin rekomendasi modul — deterministik, tanpa ML (cocok solo-dev).
 *
 * Memetakan ARKETIPE pesantren (preset yang dipilih di Setup Pesantren, disimpan
 * sebagai Setting 'setup.preset') ke set modul yang umum dibutuhkan, agar Pusat
 * Modul bisa menyarankan "pasang ini dulu" alih-alih membanjiri 13 pilihan.
 *
 * DATA statis (pola sama UnitSetupRegistry/KebijakanRegistry) — tanpa tabel.
 *
 * Catatan jujur: peta arketipe→modul ini default awal dan PERLU divalidasi ahli
 * pesantren (lihat BLUEPRINT-PUSAT-MODUL-SELF-INSTALLER.md, keputusan #8). Bila
 * preset belum dipilih/tak dikenal → pakai FALLBACK aman (3 modul paling umum).
 */
class ModuleRecommendationRegistry
{
    /**
     * Kunci preset = key KebijakanRegistry::presets(). Nilai = modul released
     * yang disarankan untuk arketipe itu (selain modul inti yang sudah aktif).
     *
     * @var array<string, list<string>>
     */
    public const ARKETIPE_MODUL = [
        'salafiyah-kecil' => ['tahfidz', 'kegiatan', 'portal_wali'],
        'modern-multi-lembaga' => ['akademik', 'ppdb', 'portal_wali', 'mutu'],
    ];

    /** Saran aman bila arketipe tak diketahui — 3 modul paling umum lintas pesantren. */
    public const FALLBACK = ['akademik', 'ppdb', 'portal_wali'];

    /**
     * Modul yang disarankan untuk sebuah arketipe (atau fallback bila null/asing).
     *
     * @return list<string>
     */
    public static function untukArketipe(?string $preset): array
    {
        if ($preset !== null && isset(self::ARKETIPE_MODUL[$preset])) {
            return self::ARKETIPE_MODUL[$preset];
        }

        return self::FALLBACK;
    }

    /**
     * Modul yang disarankan untuk tenant aktif — membaca arketipe dari Setting
     * 'setup.preset' (diisi Setup Pesantren). Toleran: gagal baca → fallback.
     *
     * @return list<string>
     */
    public static function untukTenantSaatIni(): array
    {
        try {
            $preset = Setting::getTenantStringValue('setup.preset');
        } catch (\Throwable) {
            $preset = null;
        }

        return self::untukArketipe(is_string($preset) && $preset !== '' ? $preset : null);
    }
}
