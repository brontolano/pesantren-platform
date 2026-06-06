<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Keuangan\Models\Tagihan;

class TagihanPpdb extends Model
{
    use BelongsToTenant;

    protected $table = 'tagihan_ppdb';

    protected $fillable = [
        'tenant_id',
        'calon_santri_id',
        'keuangan_tagihan_id',
        'tipe',
        'nominal',
        'jatuh_tempo',
        'status',
        'keterangan',
        'bukti_transfer',
        'bukti_diunggah_at',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'jatuh_tempo' => 'date',
        'bukti_diunggah_at' => 'datetime',
    ];

    public function calonSantri(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class, 'calon_santri_id');
    }

    public function keuanganTagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class, 'keuangan_tagihan_id');
    }
}
