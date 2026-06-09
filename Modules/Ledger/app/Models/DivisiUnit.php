<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DivisiUnit extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'divisi_unit';

    protected $fillable = [
        'tenant_id',
        'kode',
        'nama_divisi',
        'tipe_layanan',
        'is_komersial',
        'is_active',
    ];

    protected $casts = [
        'is_komersial' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function jurnalBaris(): HasMany
    {
        return $this->hasMany(JurnalBaris::class, 'divisi_id');
    }
}
