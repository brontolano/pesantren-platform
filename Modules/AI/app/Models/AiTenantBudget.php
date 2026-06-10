<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A002 — anggaran kredit AI bulanan + kill-switch per tenant (CENTRAL).
 * Baris ini juga titik kunci FOR UPDATE serialisasi tulis ledger per tenant.
 */
class AiTenantBudget extends Model
{
    protected $table = 'ai_tenant_budget';

    protected $fillable = [
        'tenant_id',
        'anggaran_kredit_bulanan',
        'ambang_alert_persen',
        'periode_berjalan',
        'terpakai_periode',
        'kill_switch_aktif',
        'alert_terkirim_pada',
    ];

    protected $casts = [
        'anggaran_kredit_bulanan' => 'decimal:4',
        'ambang_alert_persen' => 'integer',
        'periode_berjalan' => 'date',
        'terpakai_periode' => 'decimal:4',
        'kill_switch_aktif' => 'boolean',
        'alert_terkirim_pada' => 'datetime',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
