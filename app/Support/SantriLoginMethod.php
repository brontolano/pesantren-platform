<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Metode login App Santri yang DITAWARKAN pesantren.online — tiap pesantren
 * memilih sesuai kebutuhan (Setting tenant 'santri_login_methods').
 *
 * - nis_pin: NIS + PIN 6 digit (default, selalu tersedia, software-ready).
 * - qr: scan kartu santri ber-QR (payload NIS) lalu PIN — software-ready.
 * - fingerprint: WebAuthn sidik jari di perangkat pondok — butuh perangkat.
 * - nfc: kartu/gelang NFC + PIN — butuh pembaca NFC.
 */
class SantriLoginMethod
{
    public const SETTING_KEY = 'santri_login_methods';

    /** Semua metode + metadata. */
    public const METODE = [
        'nis_pin' => ['label' => 'NIS + PIN', 'desc' => 'Santri ketik NIS dan PIN 6 angka.', 'siap' => true, 'wajib' => true],
        'qr' => ['label' => 'Scan QR Kartu Santri', 'desc' => 'Pindai kartu santri (QR berisi NIS), lalu masukkan PIN.', 'siap' => true, 'wajib' => false],
        'fingerprint' => ['label' => 'Sidik Jari (WebAuthn)', 'desc' => 'Login sidik jari di perangkat pondok. Butuh perangkat pendukung.', 'siap' => false, 'wajib' => false],
        'nfc' => ['label' => 'Kartu/Gelang NFC', 'desc' => 'Tempel kartu NFC + PIN. Butuh pembaca NFC.', 'siap' => false, 'wajib' => false],
    ];

    /** Metode aktif untuk tenant ini (selalu menyertakan nis_pin). */
    public static function enabled(): array
    {
        $raw = Setting::getTenantValue(self::SETTING_KEY, null);
        $list = is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];

        // nis_pin wajib selalu ada; saring hanya yang dikenal.
        $list = array_values(array_unique(array_merge(['nis_pin'], array_intersect($list, array_keys(self::METODE)))));

        return $list;
    }

    public static function isEnabled(string $metode): bool
    {
        return in_array($metode, self::enabled(), true);
    }

    /** Payload untuk halaman login: metode aktif + metadata. */
    public static function forLogin(): array
    {
        return array_map(
            fn (string $key): array => ['key' => $key] + self::METODE[$key],
            self::enabled(),
        );
    }
}
