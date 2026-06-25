<?php

namespace Modules\Kesehatan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class SesiKonseling extends Model
{
    use BelongsToTenant;

    protected $table = 'sesi_konseling';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'tanggal',
        'topik',
        'deskripsi',
        'rekomendasi',
        'status',
        'konselor_id',
        'catatan_lanjutan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function konselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'konselor_id');
    }
}
