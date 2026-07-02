<?php

namespace Modules\Ledger\Support;

/**
 * Token desain tema keuangan (sumber tunggal) — dikonsumsi Filament (->colors)
 * & Vue/PWA (CSS vars). Override per tenant via setting branding (white-label §14).
 */
class DesignToken
{
    public const PRIMARY = '#0A5C36';   // Forest Green

    public const ACCENT = '#D4AF37';    // Gold

    public const SURFACE = '#FDFBF7';   // Cream

    public const SUCCESS = '#0A5C36';

    public const WARNING = '#D4AF37';

    public const DANGER = '#B3261E';

    public const FONT_DISPLAY = 'Marcellus';

    public const FONT_BODY = 'Plus Jakarta Sans';

    /** Palette untuk Filament panel `->colors([...])`. */
    public static function filamentColors(): array
    {
        return [
            'primary' => self::PRIMARY,
            'gold' => self::ACCENT,
            'success' => self::SUCCESS,
            'warning' => self::WARNING,
            'danger' => self::DANGER,
        ];
    }

    /** Warna badge zona/status (kehalalan, DSCR, tunggakan). */
    public static function zona(string $level): string
    {
        return match ($level) {
            'aman', 'halal', 'lunas' => self::SUCCESS,
            'waspada', 'syubhat', 'sebagian' => self::WARNING,
            default => self::DANGER, // bahaya, non_halal, menunggak
        };
    }
}
