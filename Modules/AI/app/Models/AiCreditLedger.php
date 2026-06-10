<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A002 — ledger kredit AI per tenant. Tabel CENTRAL, APPEND-ONLY (trigger
 * PostgreSQL menolak UPDATE/DELETE) — model ini hanya untuk INSERT dan baca.
 * JANGAN tambahkan fitur edit/hapus di atasnya.
 */
class AiCreditLedger extends Model
{
    public const JENIS_ALOKASI = 'alokasi';

    public const JENIS_TOPUP = 'topup';

    public const JENIS_PEMAKAIAN = 'pemakaian';

    public const JENIS_PENYESUAIAN = 'penyesuaian';

    protected $table = 'ai_credit_ledger';

    /** Append-only: hanya created_at, tanpa updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'jenis',
        'jumlah_kredit',
        'saldo_setelah',
        'referensi',
        'idempotency_key',
        'keterangan',
        'created_at',
    ];

    protected $casts = [
        'jumlah_kredit' => 'decimal:4',
        'saldo_setelah' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
