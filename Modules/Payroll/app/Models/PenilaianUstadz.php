<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * WP-20 — Penilaian kualitatif mudir per pegawai per tahun.
 * Terpisah dari rubrik_skor (yang ber-FK santri_id NOT NULL, khusus santri).
 */
class PenilaianUstadz extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'penilaian_ustadz';

    protected $fillable = [
        'tenant_id', 'employee_id', 'tahun', 'skor', 'catatan', 'dinilai_oleh',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'skor' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }

    /** Rata-rata skor kriteria (null bila belum ada skor). */
    public function rataRata(): ?float
    {
        $skor = array_filter(
            array_map('floatval', array_values($this->skor ?? [])),
            fn ($v) => $v >= 0,
        );

        return $skor === [] ? null : round(array_sum($skor) / count($skor), 2);
    }
}
