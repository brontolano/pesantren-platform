<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Ledger\Models\Akun;

/**
 * Item inventory koperasi/kantin — stok + valuasi average cost (harga_pokok).
 */
class InventoryItem extends Model
{
    use BelongsToTenant;

    protected $table = 'inventory_item';

    protected $fillable = [
        'tenant_id', 'kode', 'nama', 'satuan',
        'harga_jual', 'harga_pokok', 'stok',
        'akun_persediaan_id', 'akun_hpp_id', 'aktif',
    ];

    protected $casts = [
        'harga_jual' => 'decimal:2',
        'harga_pokok' => 'decimal:2',
        'stok' => 'decimal:3',
        'akun_persediaan_id' => 'integer',
        'akun_hpp_id' => 'integer',
        'aktif' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(InventoryMutasi::class, 'item_id');
    }

    public function stokLokasi(): HasMany
    {
        return $this->hasMany(InventoryStokLokasi::class, 'item_id');
    }

    public function batch(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'item_id');
    }

    /** Stok pada satu lokasi (0 bila belum ada baris). */
    public function stokDiLokasi(int $lokasiId): float
    {
        return (float) ($this->stokLokasi()->where('lokasi_id', $lokasiId)->value('stok') ?? 0);
    }

    public function akunPersediaan(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_persediaan_id');
    }

    public function akunHpp(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_hpp_id');
    }

    /** Nilai persediaan saat ini (stok × harga pokok). */
    public function getNilaiPersediaanAttribute(): float
    {
        return round((float) $this->stok * (float) $this->harga_pokok, 2);
    }
}
