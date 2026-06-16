<?php

namespace App\Support;

/**
 * Peta modul → unit setup (UnitSetupRegistry) + langkah WAJIB + prasyarat antar-modul.
 *
 * Mengisi dua celah yang TIDAK dimodelkan di ModuleRegistry: (1) modul mana butuh
 * setup apa sebelum dianggap "siap pakai", (2) dependency antar-modul (mis. pajak
 * butuh ledger). Disimpan sebagai DATA statis — pola sama dengan
 * UnitSetupRegistry::units() & KebijakanRegistry::presets() — tanpa tabel/migrasi.
 *
 * "Siap" sebuah modul = SEMUA langkah WAJIB-nya tuntas (dibaca live dari DB lewat
 * UnitSetupRegistry). Modul tanpa langkah wajib → siap begitu dipasang (1 klik),
 * sekaligus menambal jebakan progress() yang menganggap total==0 = belum tuntas.
 *
 * Catatan jujur: daftar langkah WAJIB di bawah adalah default rasional (data nyata
 * tetap penentu), BUKAN final — owner dapat menyesuaikan per pesantren
 * (lihat BLUEPRINT-PUSAT-MODUL-SELF-INSTALLER.md, keputusan #4).
 */
class ModuleSetupMap
{
    /**
     * modul (released non-core) => [unit => kode UnitSetupRegistry, wajib => list<step key>].
     * wajib kosong = tak ada langkah wajib → langsung siap saat dipasang.
     * Modul tak terdaftar di sini (mis. kegiatan) = tak punya unit → langsung siap.
     *
     * @var array<string, array{unit:string, wajib:list<string>}>
     */
    public const MAP = [
        'akademik' => ['unit' => 'akademik', 'wajib' => ['lembaga', 'periode']],
        'ppdb' => ['unit' => 'ppdb', 'wajib' => []],
        'tahfidz' => ['unit' => 'tahfidz', 'wajib' => ['halaqah']],
        'asrama' => ['unit' => 'kesantrian', 'wajib' => ['asrama']],
        'kesehatan' => ['unit' => 'kesantrian', 'wajib' => []],
        'portal_wali' => ['unit' => 'kesantrian', 'wajib' => []],
        'mutu' => ['unit' => 'pengaturan', 'wajib' => []],
        'ledger' => ['unit' => 'akuntansi', 'wajib' => ['coa']],
        'pajak' => ['unit' => 'akuntansi', 'wajib' => []],
        'payroll' => ['unit' => 'kepegawaian', 'wajib' => ['pegawai']],
        'pembiayaan' => ['unit' => 'sarana_logistik', 'wajib' => []],
        'pengadaan' => ['unit' => 'sarana_logistik', 'wajib' => []],
    ];

    /**
     * Prasyarat antar-modul: modul => list<modul prasyarat>.
     * Dipakai Pusat Modul agar memasang prasyarat lebih dulu (resolusi topologis).
     *
     * @var array<string, list<string>>
     */
    public const DEPS = [
        'pajak' => ['ledger'],
        'payroll' => ['ledger'],
        'pembiayaan' => ['ledger'],
        'pengadaan' => ['ledger'],
    ];

    /** Kode unit setup untuk modul (null = tak punya unit/langkah → instan siap). */
    public static function unit(string $moduleKey): ?string
    {
        return self::MAP[$moduleKey]['unit'] ?? null;
    }

    /**
     * Prasyarat langsung (modul yang wajib dipasang lebih dulu).
     *
     * @return list<string>
     */
    public static function deps(string $moduleKey): array
    {
        return self::DEPS[$moduleKey] ?? [];
    }

    /**
     * Apakah modul "siap pakai": SEMUA langkah WAJIB-nya tuntas (dibaca live dari
     * DB). Modul tanpa langkah wajib → true (siap begitu dipasang). HARUS dipanggil
     * dalam konteks tenant aktif karena UnitSetupRegistry membaca DB tenant.
     * Toleran: kegagalan baca dianggap belum-siap (tak pernah melempar).
     */
    public static function ready(string $moduleKey): bool
    {
        $def = self::MAP[$moduleKey] ?? null;
        if ($def === null) {
            return true; // tak punya unit → langsung siap
        }
        if ($def['wajib'] === []) {
            return true; // tak ada langkah wajib → langsung siap (fix jebakan total==0)
        }

        try {
            $steps = UnitSetupRegistry::units()[$def['unit']]['steps'] ?? [];
        } catch (\Throwable) {
            return false;
        }

        $done = [];
        foreach ($steps as $s) {
            $done[$s['key']] = (bool) ($s['done'] ?? false);
        }

        foreach ($def['wajib'] as $key) {
            if (empty($done[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Langkah WAJIB modul beserta status done (untuk kartu Pusat Modul / wizard).
     *
     * @return list<array{key:string, label:string, desc:string, done:bool, url:?string}>
     */
    public static function requiredSteps(string $moduleKey): array
    {
        $def = self::MAP[$moduleKey] ?? null;
        if ($def === null || $def['wajib'] === []) {
            return [];
        }

        try {
            $steps = UnitSetupRegistry::units()[$def['unit']]['steps'] ?? [];
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(
            $steps,
            fn (array $s): bool => in_array($s['key'], $def['wajib'], true),
        ));
    }
}
