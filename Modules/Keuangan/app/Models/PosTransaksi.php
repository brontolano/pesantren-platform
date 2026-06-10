<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * Satu transaksi tunai POS (pemasukan/pengeluaran), terikat ke sesi kasir.
 * jurnal_id terisi bila posting Ledger dilakukan langsung oleh PosKasService;
 * untuk handler delegasi (tabungan/pembayaran) jurnal dibuat service terkait.
 *
 * G3-21: metode_bayar/harga_kotor/diskon/poin = enrichment ritel (additif).
 * nominal SELALU = netto (harga_kotor − diskon) → posting tetap seimbang.
 */
class PosTransaksi extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'pos_transaksi';

    protected $fillable = [
        'tenant_id', 'sesi_id', 'kategori_id', 'jenis', 'nominal',
        'santri_id', 'ref_type', 'ref_id', 'jurnal_id', 'keterangan', 'dibuat_oleh',
        // G3-21 enrichment
        'metode_bayar', 'harga_kotor', 'diskon', 'poin',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'harga_kotor' => 'decimal:2',
        'diskon' => 'decimal:2',
        'poin' => 'decimal:2',
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
