<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anggaran (RKAM) — pagu belanja per akun + dimensi (divisi/sumber dana) + tahun.
 * Dibandingkan dengan realisasi (jurnal) oleh AnggaranGuardService.
 *   mode 'peringatan' = boleh lampaui (hanya ditandai),
 *   mode 'blokir'     = transaksi pelampau ditolak.
 */
class Anggaran extends Model
{
    use BelongsToTenant;

    protected $table = 'anggaran';

    protected $fillable = [
        'tenant_id',
        'tahun_anggaran',
        'akun_id',
        'divisi_id',
        'sumber_dana_id',
        'nominal_anggaran',
        'mode',
        'keterangan',
    ];

    protected $casts = [
        'nominal_anggaran' => 'decimal:2',
    ];

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(DivisiUnit::class, 'divisi_id');
    }

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }
}
