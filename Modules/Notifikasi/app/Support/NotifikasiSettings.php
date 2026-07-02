<?php

namespace Modules\Notifikasi\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Resolver pengaturan notifikasi per-tenant (disimpan di tabel `settings`)
 * dengan fallback ke config/env. Dipakai oleh service WA/email & job broadcast
 * agar pengaturan yang diatur staf di panel benar-benar berpengaruh.
 *
 * Sumber kebenaran UI: App\Filament\Pages\PengaturanNotifikasi.
 */
class NotifikasiSettings
{
    public const KEY_WA_ENABLED = 'notif_wa_enabled';

    public const KEY_EMAIL_ENABLED = 'notif_email_enabled';

    public const KEY_WA_SIGNATURE = 'notif_wa_signature';

    public const KEY_BROADCAST_INTERVAL = 'notif_broadcast_interval';

    public const KEY_WA_WEBHOOK = 'notif_wa_webhook_url';

    public const KEY_WA_API_KEY = 'notif_wa_api_key';

    /** Notifikasi WhatsApp aktif? Default: aktif. */
    public static function waEnabled(): bool
    {
        return (bool) data_get(Setting::getTenantValue(self::KEY_WA_ENABLED), 'enabled', true);
    }

    /** Notifikasi email aktif? Default: aktif. */
    public static function emailEnabled(): bool
    {
        return (bool) data_get(Setting::getTenantValue(self::KEY_EMAIL_ENABLED), 'enabled', true);
    }

    /**
     * URL webhook n8n per-tenant (opsional). Fallback: config notifikasi.n8n_webhook_url.
     * Memungkinkan tenant memakai pipa WA/nomor pengirim sendiri tanpa ubah env global.
     */
    public static function webhookUrl(): ?string
    {
        $value = self::reveal(data_get(Setting::getTenantValue(self::KEY_WA_WEBHOOK), 'url'));

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $fallback = config('notifikasi.n8n_webhook_url');

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    /**
     * API key WAHA (opsional) — override per-tenant di Setting, fallback ke config/env.
     * Nilai di Setting disimpan terenkripsi (lihat conceal()); dibaca dgn reveal()
     * yang kompat-mundur terhadap nilai plaintext lama.
     */
    public static function wahaApiKey(): ?string
    {
        $value = self::reveal(data_get(Setting::getTenantValue(self::KEY_WA_API_KEY), 'key'));

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $fallback = config('notifikasi.waha_api_key');

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    /**
     * Enkripsi nilai sensitif sebelum SIMPAN ke Setting. Mengembalikan null bila
     * kosong. Dipakai oleh PengaturanNotifikasi::save().
     */
    public static function conceal(?string $plain): ?string
    {
        $plain = is_string($plain) ? trim($plain) : '';

        if ($plain === '') {
            return null;
        }

        return Crypt::encryptString($plain);
    }

    /**
     * Dekripsi nilai dari Setting. KOMPAT-MUNDUR: nilai lama yang belum terenkripsi
     * (plaintext) akan gagal decrypt → dianggap plaintext & dikembalikan apa adanya,
     * agar notifikasi produksi tak putus saat migrasi enkripsi bertahap.
     */
    public static function reveal(mixed $stored): ?string
    {
        if (! is_string($stored) || $stored === '') {
            return is_string($stored) ? $stored : null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            // Nilai lama pra-enkripsi (plaintext) — pakai apa adanya.
            return $stored;
        }
    }

    /** Tanda tangan/footer yang ditambahkan ke akhir setiap pesan WA (opsional). */
    public static function signature(): ?string
    {
        $value = data_get(Setting::getTenantValue(self::KEY_WA_SIGNATURE), 'text');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** Jeda antar-pesan broadcast (detik). Fallback: config notifikasi.broadcast_interval_seconds. */
    public static function broadcastInterval(): int
    {
        $value = data_get(Setting::getTenantValue(self::KEY_BROADCAST_INTERVAL), 'seconds');

        if (is_numeric($value)) {
            return max(0, min(300, (int) $value));
        }

        return max(0, (int) config('notifikasi.broadcast_interval_seconds', 10));
    }
}
