<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SumberDana extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'sumber_dana';

    protected $fillable = [
        'tenant_id',
        'kode_dana',
        'nama_sumber',
        'kategori',
        'kehalalan',
        'is_dana_kebajikan',
        'rezim',
        'is_wakaf',
        'is_terikat',
        'is_active',
    ];

    protected $casts = [
        'is_dana_kebajikan' => 'boolean',
        'is_wakaf' => 'boolean',
        'is_terikat' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** Dana bukan-halal (syubhat/non_halal) — wajib diperlakukan sebagai Dana Kebajikan. */
    public function bukanHalal(): bool
    {
        return in_array($this->kehalalan, ['syubhat', 'non_halal'], true);
    }

    public function jurnalBaris(): HasMany
    {
        return $this->hasMany(JurnalBaris::class, 'sumber_dana_id');
    }
}
