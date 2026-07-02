<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * gap-130 — Master Gelombang PPDB terstruktur.
 *
 * Referensi gelombang per tenant (additif). Mengikuti pola tenant-scoping
 * model PPDB lain (CalonSantri): trait BelongsToTenant memasang TenantScope
 * global + auto-isi tenant_id saat creating.
 *
 * NON-UANG: `biaya_pendaftaran` hanya nominal konfigurasi referensi — TIDAK
 * menyentuh Ledger / Tagihan / posting jurnal.
 */
class GelombangPpdb extends Model
{
    use BelongsToTenant;

    protected $table = 'gelombang_ppdb';

    protected $fillable = [
        'tenant_id',
        'nama',
        'tahun_ajaran',
        'tanggal_buka',
        'tanggal_tutup',
        'kuota',
        'biaya_pendaftaran',
        'aktif',
    ];

    protected $casts = [
        'tanggal_buka' => 'date',
        'tanggal_tutup' => 'date',
        'kuota' => 'integer',
        'biaya_pendaftaran' => 'integer',
        'aktif' => 'boolean',
    ];
}
