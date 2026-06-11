<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * WP-13 — Tarif poin khidmah per tenant per (jabatan × jenis sesi).
 * jabatan_id NULL = tarif default tenant (fallback bila jabatan pegawai
 * tidak punya tarif spesifik). Policy-first: tiap tenant menentukan
 * tarifnya sendiri.
 *
 * ⚠️ Poin boleh pecahan (decimal 12,2), TAPI SlipGaji.poin bertipe INTEGER
 * (mesin slip lama men-cast (int) — tak diubah). KhidmahAggregatorService::
 * generateSlip membulatkan Σ poin (round half-up: 10.75 → 11) sebelum
 * diserahkan ke PayrollService — JANGAN kirim poin pecahan langsung ke
 * buatSlip. Rekomendasi form tarif: pakai tarif bulat bila ingin slip 1:1.
 */
class TarifKhidmah extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'tarif_khidmah';

    /** @var array<string, string> nilai => label tampilan */
    public const JENIS_SESI = [
        'jam_formal' => 'Jam Mengajar Formal',
        'sesi_diniyah' => 'Sesi Diniyah',
        'sesi_tahfidz' => 'Sesi Tahfidz',
        'malam_musyrif' => 'Malam Jaga Musyrif',
        'hadir_kantor' => 'Kehadiran Kantor',
    ];

    protected $fillable = [
        'tenant_id', 'jabatan_id', 'jenis_sesi', 'poin', 'aktif',
    ];

    protected $casts = [
        'poin' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
