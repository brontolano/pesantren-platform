<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;

/**
 * G2-11 — Pengajuan cuti/leave. Approvable (F1, field gate 'jumlah_hari') + Auditable.
 */
class Cuti extends Model
{
    use Approvable, Auditable, BelongsToTenant;

    protected $table = 'cuti';

    /**
     * WP-17 — Satu-satunya sumber kebenaran daftar jenis. Kolom DB string(20)
     * bebas tanpa CHECK — validasi app-level (form & service WAJIB baca ini).
     *
     * @var array<string, string> nilai => label tampilan
     */
    public const JENIS = [
        'tahunan' => 'Cuti Tahunan',
        'sakit' => 'Sakit',
        'penting' => 'Keperluan Penting',
        'melahirkan' => 'Melahirkan',
        'pulang_kampung' => 'Izin Pulang Kampung',
        'keperluan_keluarga' => 'Izin Keperluan Keluarga',
        'dinas' => 'Izin Dinas',
        'lainnya' => 'Lainnya',
    ];

    /**
     * WP-17 — Jenis izin harian: default is_berbayar TRUE (tak memotong gaji,
     * jalur potongan WP-14 hanya untuk cuti tak berbayar).
     *
     * @var list<string>
     */
    public const JENIS_IZIN_HARIAN = ['pulang_kampung', 'keperluan_keluarga', 'dinas'];

    protected $fillable = [
        'tenant_id', 'employee_id', 'jenis', 'is_berbayar', 'tanggal_mulai', 'tanggal_selesai',
        'jumlah_hari', 'alasan', 'status', 'disetujui_oleh', 'disetujui_pada', 'diajukan_oleh',
        'client_ref',
    ];

    protected $casts = [
        'is_berbayar' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'jumlah_hari' => 'integer',
        'disetujui_pada' => 'datetime',
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
