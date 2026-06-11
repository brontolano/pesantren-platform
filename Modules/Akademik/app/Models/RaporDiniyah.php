<?php

namespace Modules\Akademik\Models;

use App\Models\Setting;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-10 — Rapor Diniyah: snapshot JSON per blok, satu baris per
 * santri+periode (unique). Blok mana yang TAMPIL saat cetak diatur per
 * tenant via Setting `akademik.rapor_diniyah_blok` (policy-first) — data
 * snapshot tetap lengkap, hanya render yang difilter.
 */
class RaporDiniyah extends Model
{
    use Auditable, BelongsToTenant;

    /** Daftar blok yang dikenal + label cetak. Urutan = urutan cetak. */
    public const BLOK = [
        'nilai_imtihan' => 'Nilai Imtihan',
        'capaian_kitab' => 'Capaian Kitab',
        'tahfidz' => 'Tahfidz',
        'ubudiyah_akhlak' => 'Ubudiyah & Akhlak',
        'kehadiran_kegiatan' => 'Kehadiran Kegiatan',
        'disiplin' => 'Disiplin',
    ];

    public const SETTING_BLOK = 'akademik.rapor_diniyah_blok';

    protected $table = 'rapor_diniyah';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'periode_akademik_id',
        'blok',
        'catatan_mustahiq',
        'catatan_pengasuh',
        'generated_at',
    ];

    protected $casts = [
        'blok' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * Blok aktif tenant saat ini (Setting JSON array nama blok; default
     * SEMUA blok). Nama tak dikenal diabaikan; urutan mengikuti self::BLOK.
     *
     * @return array<int, string>
     */
    public static function blokAktif(): array
    {
        $value = Setting::getTenantValue(self::SETTING_BLOK, null);

        if (! is_array($value) || $value === []) {
            return array_keys(self::BLOK);
        }

        $aktif = array_values(array_intersect(array_keys(self::BLOK), $value));

        // Semua nama tak dikenal (typo total) → rapor kosong; fallback semua.
        return $aktif === [] ? array_keys(self::BLOK) : $aktif;
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }
}
