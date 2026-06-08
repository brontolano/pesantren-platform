<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Akun extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'akun';

    public const TIPE = ['ASET', 'KEWAJIBAN', 'EKUITAS', 'PENDAPATAN', 'BEBAN'];

    // Saldo normal baku per tipe akun (debit menambah ASET/BEBAN; kredit menambah sisanya).
    public const SALDO_NORMAL = [
        'ASET' => 'debit',
        'BEBAN' => 'debit',
        'KEWAJIBAN' => 'kredit',
        'EKUITAS' => 'kredit',
        'PENDAPATAN' => 'kredit',
    ];

    protected $fillable = [
        'tenant_id',
        'kode',
        'nama',
        'tipe',
        'saldo_normal',
        'parent_id',
        'is_kas_bank',
        'peruntukan',
        'boleh_dana_kebajikan',
        'is_postable',
        'is_active',
    ];

    protected $casts = [
        'is_kas_bank' => 'boolean',
        'boleh_dana_kebajikan' => 'boolean',
        'is_postable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function jurnalBaris(): HasMany
    {
        return $this->hasMany(JurnalBaris::class, 'akun_id');
    }

    public function scopeKasBank($query)
    {
        return $query->where('is_kas_bank', true);
    }
}
