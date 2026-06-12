<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;

/**
 * WP-23 — Kasbon / pinjaman pegawai. Approvable (F1 — pola Cuti persis:
 * ajukanPersetujuan saat pengajuan, pastikanBolehLanjut sebelum pencairan).
 *
 * Lifecycle status: diajukan → disetujui → berjalan → lunas; atau batal.
 * sisa_pokok berkurang HANYA saat slip gaji FINAL (KasbonService via
 * PayrollService::finalisasi) atau pelunasan dipercepat — selalu di dalam
 * transaksi + lockForUpdate.
 */
class PinjamanPegawai extends Model
{
    use Approvable, Auditable, BelongsToTenant;

    protected $table = 'pinjaman_pegawai';

    public const STATUS = ['diajukan', 'disetujui', 'berjalan', 'lunas', 'batal'];

    protected $fillable = [
        'tenant_id', 'employee_id', 'nominal', 'tanggal', 'cicilan_bulanan',
        'sisa_pokok', 'status', 'jurnal_pencairan_id', 'disetujui_oleh', 'catatan',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'cicilan_bulanan' => 'decimal:2',
        'sisa_pokok' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}
