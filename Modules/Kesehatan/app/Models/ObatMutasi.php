<?php

namespace Modules\Kesehatan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * WP-28 — Jejak mutasi stok obat (append-only).
 * jenis masuk|keluar; ref morph opsional (mis. KunjunganUks).
 */
class ObatMutasi extends Model
{
    use BelongsToTenant;

    protected $table = 'obat_mutasi';

    public const JENIS = ['masuk', 'keluar'];

    protected $fillable = [
        'tenant_id',
        'obat_id',
        'jenis',
        'jumlah',
        'ref_type',
        'ref_id',
        'keterangan',
        'user_id',
    ];

    protected $casts = [
        'jumlah' => 'integer',
    ];

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class, 'obat_id');
    }

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
