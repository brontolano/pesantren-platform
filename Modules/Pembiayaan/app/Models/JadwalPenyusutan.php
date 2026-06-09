<?php

namespace Modules\Pembiayaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * G2-13 — Satu baris beban penyusutan per aset per periode.
 */
class JadwalPenyusutan extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'jadwal_penyusutan';

    protected $fillable = [
        'tenant_id', 'aset_id', 'urutan', 'periode', 'nilai_awal', 'beban_penyusutan',
        'akumulasi', 'nilai_akhir', 'status', 'jurnal_id',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'nilai_awal' => 'decimal:2',
        'beban_penyusutan' => 'decimal:2',
        'akumulasi' => 'decimal:2',
        'nilai_akhir' => 'decimal:2',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}
