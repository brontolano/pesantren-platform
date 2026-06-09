<?php

namespace Modules\Core\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan persetujuan: kapan sebuah model butuh approval + siapa penyetujunya.
 * mode 'wajib' = transaksi tertahan sampai disetujui; 'peringatan' = lolos (ditandai).
 */
class ApprovalRule extends Model
{
    use BelongsToTenant;

    protected $table = 'approval_rule';

    /** Tipe model yang bisa di-approve (label untuk UI). Tambah di sini saat rollout. */
    public const APPROVABLE = [
        \Modules\Pengadaan\Models\Belanja::class => 'Belanja / Pengadaan',
        \Modules\Payroll\Models\SlipGaji::class => 'Slip Gaji',
        \Modules\Keuangan\Models\Pembayaran::class => 'Pembayaran',
        \Modules\Ledger\Models\Jurnal::class => 'Jurnal Manual',
    ];

    protected $fillable = [
        'tenant_id', 'approvable_type', 'nama', 'field', 'operator', 'nilai',
        'approver_role', 'mode', 'urutan', 'aktif',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    public function scopeUntukTipe(Builder $q, string $type): Builder
    {
        return $q->where('approvable_type', $type);
    }

    /** Apakah aturan ini terpicu oleh nilai field pada $model. */
    public function terpicu(Model $model): bool
    {
        if (blank($this->field)) {
            return true; // aturan tanpa kondisi → selalu berlaku untuk tipe ini
        }

        $aktual = (float) ($model->{$this->field} ?? 0);
        $ambang = (float) $this->nilai;

        return match ($this->operator) {
            '>' => $aktual > $ambang,
            '>=' => $aktual >= $ambang,
            '=' => abs($aktual - $ambang) < 0.005,
            '<=' => $aktual <= $ambang,
            '<' => $aktual < $ambang,
            default => false,
        };
    }
}
