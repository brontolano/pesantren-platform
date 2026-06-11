<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabunganTransaksi extends Model
{
    use BelongsToTenant;

    protected $table = 'tabungan_transaksi';

    protected $fillable = [
        'tenant_id',
        'tabungan_id',
        'tipe',
        'nominal',
        'saldo_sebelum',
        'saldo_sesudah',
        'keterangan',
        'tanggal',
        'created_by',
        'ref_transaksi_id',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'saldo_sebelum' => 'decimal:2',
        'saldo_sesudah' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public function tabungan(): BelongsTo
    {
        return $this->belongsTo(Tabungan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Transaksi belanja asal (untuk baris refund_kantin). */
    public function refTransaksi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ref_transaksi_id');
    }
}
