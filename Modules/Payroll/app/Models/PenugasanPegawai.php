<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Models\Lembaga;
use Modules\Core\Concerns\Auditable;
use Modules\Payroll\Observers\PenugasanPegawaiObserver;

/**
 * WP-03 — Penugasan multi-peran: satu pegawai banyak jabatan di banyak
 * lembaga. tanggal_selesai NULL = aktif; lembaga_id NULL = lintas lembaga.
 * Observer menyinkronkan role Spatie dari mapping Jabatan.role_spatie.
 */
#[ObservedBy(PenugasanPegawaiObserver::class)]
class PenugasanPegawai extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'penugasan_pegawai';

    protected $fillable = [
        'tenant_id', 'employee_id', 'jabatan_id', 'lembaga_id',
        'tanggal_mulai', 'tanggal_selesai', 'nomor_sk', 'file_sk',
        'is_primary', 'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_primary' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id');
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'lembaga_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNull('tanggal_selesai');
    }
}
