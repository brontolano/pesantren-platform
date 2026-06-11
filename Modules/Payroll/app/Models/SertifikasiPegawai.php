<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * WP-18 — Arsip Sertifikasi pegawai (TPG/Inpassing/GBPNS/PPG). Jenis dijaga
 * CHECK constraint di Postgres + konstanta ini di app-level. Queryable untuk
 * rekap kesiapan EMIS (WP-12 menyusul).
 */
class SertifikasiPegawai extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'sertifikasi_pegawai';

    /** @var array<string, string> nilai => label tampilan (selaras CHECK DB) */
    public const JENIS = [
        'tpg' => 'TPG (Tunjangan Profesi Guru)',
        'inpassing' => 'Inpassing',
        'gbpns' => 'GBPNS',
        'ppg' => 'PPG',
    ];

    protected $fillable = [
        'tenant_id', 'employee_id', 'jenis', 'nrg', 'nomor_sk', 'nominal',
        'berlaku_mulai', 'berlaku_selesai', 'file',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'berlaku_mulai' => 'date',
        'berlaku_selesai' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
