<?php

namespace Modules\Core\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

/**
 * Aturan persetujuan: kapan sebuah model butuh approval + siapa penyetujunya.
 * mode 'wajib' = transaksi tertahan sampai disetujui; 'peringatan' = lolos (ditandai).
 */
class ApprovalRule extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'approval_rule';

    /** Tipe model yang bisa di-approve (label untuk UI). Tambah di sini saat rollout. */
    public const APPROVABLE = [
        \Modules\Pengadaan\Models\Belanja::class => 'Belanja / Pengadaan',
        \Modules\Pengadaan\Models\PurchaseOrder::class => 'Purchase Order',
        \Modules\Payroll\Models\SlipGaji::class => 'Slip Gaji',
        \Modules\Keuangan\Models\Pembayaran::class => 'Pembayaran',
        \Modules\Ledger\Models\Jurnal::class => 'Jurnal Manual',
        \Modules\Payroll\Models\Cuti::class => 'Cuti / Leave',
    ];

    /**
     * Field numerik valid sebagai ambang pemicu, per tipe model. Whitelist ini
     * mencegah salah-ketik field yang membuat rule diam-diam tak aktif
     * (terpicu() membaca $model->{field} ?? 0). UI ApprovalRuleResource memakai ini.
     */
    public const FIELD_OPTIONS = [
        \Modules\Pengadaan\Models\Belanja::class => ['total' => 'Total (DPP + PPN)'],
        \Modules\Pengadaan\Models\PurchaseOrder::class => ['total' => 'Total PO'],
        \Modules\Payroll\Models\SlipGaji::class => ['bruto' => 'Bruto (gaji kotor)'],
        \Modules\Keuangan\Models\Pembayaran::class => ['nominal' => 'Nominal'],
        \Modules\Ledger\Models\Jurnal::class => ['total_debit' => 'Total Debit'],
        \Modules\Payroll\Models\Cuti::class => ['jumlah_hari' => 'Jumlah Hari'],
    ];

    /** Operator perbandingan yang didukung engine terpicu(). */
    public const OPERATORS = [
        '>=' => '≥ (lebih dari sama dengan)',
        '>' => '> (lebih dari)',
        '=' => '= (sama dengan)',
        '<=' => '≤ (kurang dari sama dengan)',
        '<' => '< (kurang dari)',
    ];

    /** Semua field valid lintas-tipe (untuk validasi engine/test). */
    public static function fieldValid(?string $approvableType, ?string $field): bool
    {
        if (blank($field)) {
            return true; // kosong = selalu berlaku
        }

        return array_key_exists($field, self::FIELD_OPTIONS[$approvableType] ?? []);
    }

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
