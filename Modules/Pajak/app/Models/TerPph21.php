<?php

namespace Modules\Pajak\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TerPph21 extends Model
{
    use BelongsToTenant;

    protected $table = 'ter_pph21';

    protected $fillable = [
        'tenant_id',
        'kategori',
        'batas_bawah',
        'batas_atas',
        'tarif_persen',
        'dasar',
        'is_active',
    ];

    protected $casts = [
        'batas_bawah' => 'decimal:2',
        'batas_atas' => 'decimal:2',
        'tarif_persen' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Cari tarif TER untuk bruto bulanan tertentu.
     */
    public static function cariTarif(string $kategori, float $bruto): ?self
    {
        return static::where('kategori', $kategori)
            ->where('is_active', true)
            ->where('batas_bawah', '<=', $bruto)
            ->where('batas_atas', '>', $bruto)
            ->first();
    }
}
