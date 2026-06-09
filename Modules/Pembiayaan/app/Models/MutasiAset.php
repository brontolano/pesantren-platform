<?php

namespace Modules\Pembiayaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;

/**
 * G2-13 — Mutasi lokasi & pelepasan (disposal) aset.
 * Disposal Approvable (gerbang F1) + Auditable. WAKAF: tanpa P/L (tanpa_pl=true).
 */
class MutasiAset extends Model
{
    use Approvable, Auditable, BelongsToTenant;

    protected $table = 'mutasi_aset';

    protected $fillable = [
        'tenant_id', 'aset_id', 'tipe', 'tanggal', 'dari_lokasi', 'ke_lokasi',
        'harga_jual', 'nilai_buku', 'laba_rugi', 'tanpa_pl', 'keterangan',
        'status', 'jurnal_id', 'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'harga_jual' => 'decimal:2',
        'nilai_buku' => 'decimal:2',
        'laba_rugi' => 'decimal:2',
        'tanpa_pl' => 'boolean',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}
