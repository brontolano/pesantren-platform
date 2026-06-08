<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Kebijakan kehalalan (configurable, disahkan otoritas syar'i pesantren).
 * Memetakan: sumber `kehalalan` X boleh/tidak untuk `peruntukan` Y.
 */
class KebijakanKehalalan extends Model
{
    use BelongsToTenant;

    protected $table = 'kebijakan_kehalalan';

    protected $fillable = [
        'tenant_id',
        'kehalalan_sumber',
        'peruntukan_tujuan',
        'diizinkan',
        'dasar',
    ];

    protected $casts = [
        'diizinkan' => 'boolean',
    ];
}
