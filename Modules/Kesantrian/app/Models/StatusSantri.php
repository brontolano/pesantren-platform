<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusSantri extends Model
{
    use BelongsToTenant;

    protected $table = 'status_santri';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'status',
        'tanggal_status',
        'alasan',
        'file_surat_path',
        'durasi_cuti_hari',
        'tanggal_kembali',
        'created_by',
    ];

    protected $casts = [
        'tanggal_status' => 'date',
        'tanggal_kembali' => 'date',
        'durasi_cuti_hari' => 'integer',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
