<?php

namespace Modules\Core\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Ledger\Models\Jurnal;
use Modules\Mutu\Models\MutuTemuan;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\SlipGaji;
use Modules\Pembiayaan\Models\MutasiAset;
use Modules\Pengadaan\Models\Belanja;
use Modules\Pengadaan\Models\PurchaseOrder;

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
        Belanja::class => 'Belanja / Pengadaan',
        PurchaseOrder::class => 'Purchase Order',
        SlipGaji::class => 'Slip Gaji',
        Pembayaran::class => 'Pembayaran',
        Jurnal::class => 'Jurnal Manual',
        Cuti::class => 'Cuti / Leave',
        MutasiAset::class => 'Mutasi / Pelepasan Aset',
        MutuTemuan::class => 'Temuan Mutu (Non-Conformance)',
    ];

    /**
     * Field numerik valid sebagai ambang pemicu, per tipe model. Whitelist ini
     * mencegah salah-ketik field yang membuat rule diam-diam tak aktif
     * (terpicu() membaca $model->{field} ?? 0). UI ApprovalRuleResource memakai ini.
     */
    public const FIELD_OPTIONS = [
        Belanja::class => ['total' => 'Total (DPP + PPN)'],
        PurchaseOrder::class => ['total' => 'Total PO'],
        SlipGaji::class => ['bruto' => 'Bruto (gaji kotor)'],
        Pembayaran::class => ['nominal' => 'Nominal'],
        Jurnal::class => ['total_debit' => 'Total Debit'],
        Cuti::class => ['jumlah_hari' => 'Jumlah Hari'],
        MutasiAset::class => ['harga_jual' => 'Harga Jual / Pelepasan'],
        MutuTemuan::class => ['severity_level' => 'Tingkat Keparahan (1-3)'],
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
