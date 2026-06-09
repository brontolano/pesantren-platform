<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * G3-17 — Survei Kepuasan Masyarakat/wali (SKM). Skor 1..5 per aspek.
 */
class MutuSkm extends Model
{
    use BelongsToTenant;

    protected $table = 'mutu_skm';

    protected $fillable = [
        'tenant_id', 'periode', 'aspek', 'responden', 'skor', 'komentar',
    ];

    protected $casts = [
        'skor' => 'integer',
    ];
}
