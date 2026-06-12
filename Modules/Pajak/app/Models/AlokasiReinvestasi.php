<?php

namespace Modules\Pajak\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

class AlokasiReinvestasi extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'alokasi_reinvestasi';

    protected $fillable = [
        'tenant_id', 'sisa_lebih_id', 'tanggal', 'nominal',
        'keterangan', 'ref_type', 'ref_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
    ];

    public function sisaLebih(): BelongsTo
    {
        return $this->belongsTo(SisaLebihYayasan::class, 'sisa_lebih_id');
    }
}
