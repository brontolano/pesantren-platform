<?php

namespace Modules\Notifikasi\Support;

use App\Models\Setting;

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
        $value = data_get(Setting::getTenantValue(self::KEY_WA_WEBHOOK), 'url');

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $fallback = config('notifikasi.n8n_webhook_url');

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
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
