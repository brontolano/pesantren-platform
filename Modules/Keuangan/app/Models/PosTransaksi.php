<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu transaksi tunai POS (pemasukan/pengeluaran), terikat ke sesi kasir.
 * jurnal_id terisi bila posting Ledger dilakukan langsung oleh PosKasService;
 * untuk handler delegasi (tabungan/pembayaran) jurnal dibuat service terkait.
 */
class PosTransaksi extends Model
{
    use BelongsToTenant;

    protected $table = 'pos_transaksi';

    protected $fillable = [
        'tenant_id', 'sesi_id', 'kategori_id', 'jenis', 'nominal',
        'santri_id', 'ref_type', 'ref_id', 'jurnal_id', 'keterangan', 'dibuat_oleh',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'santri_id' => 'integer',
        'jurnal_id' => 'integer',
    ];

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(PosSesi::class, 'sesi_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(PosKategori::class, 'kategori_id');
    }
}
