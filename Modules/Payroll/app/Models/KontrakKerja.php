<?php

namespace Modules\Payroll\Models;

use App\Models\Setting;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * WP-16 — Kontrak/SK pegawai (sk_gty | kontrak | sk_pengabdian | sk_dpk).
 *
 * Rantai perpanjangan: baris baru menunjuk parent_id → baris lama status
 * 'diperpanjang', file arsip lama TIDAK PERNAH ditimpa.
 */
class KontrakKerja extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'kontrak_kerja';

    /** Kunci Setting per-tenant ambang pengingat (hari), default 60. */
    public const SETTING_REMINDER_HARI = 'payroll.kontrak_reminder_hari';

    public const DEFAULT_REMINDER_HARI = 60;

    public const JENIS = [
        'sk_gty' => 'SK GTY',
        'kontrak' => 'Kontrak',
        'sk_pengabdian' => 'SK Pengabdian',
        'sk_dpk' => 'SK DPK',
    ];

    public const STATUS = [
        'aktif' => 'Aktif',
        'berakhir' => 'Berakhir',
        'diperpanjang' => 'Diperpanjang',
    ];

    protected $fillable = [
        'tenant_id', 'employee_id', 'jenis', 'nomor', 'mulai', 'selesai', 'file',
        'status', 'parent_id', 'periode_khidmat', 'lembaga_pengirim',
        'tanggal_purna', 'last_reminded_at',
    ];

    protected $casts = [
        'mulai' => 'date',
        'selesai' => 'date',
        'tanggal_purna' => 'date',
        'last_reminded_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function perpanjangan(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Ambang pengingat (hari) — policy per tenant via Setting. */
    public static function reminderHari(): int
    {
        $nilai = Setting::getTenantValue(self::SETTING_REMINDER_HARI);
        $nilai = is_array($nilai) ? ($nilai['value'] ?? null) : $nilai;

        return max(1, (int) ($nilai ?: self::DEFAULT_REMINDER_HARI));
    }

    /**
     * Kontrak aktif yang `selesai` jatuh dalam ≤ $hari ke depan (inklusif,
     * termasuk yang sudah lewat tapi belum ditandai berakhir).
     */
    public static function scopeMendekatiHabis(Builder $query, ?int $hari = null): Builder
    {
        $hari ??= self::reminderHari();

        return $query
            ->where('status', 'aktif')
            ->whereNotNull('selesai')
            ->whereDate('selesai', '<=', today()->addDays($hari));
    }
}
