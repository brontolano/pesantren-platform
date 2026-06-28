<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Models\Lembaga;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Kelas;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;

/**
 * Tagihan Rutin — aturan "langganan" tagihan yang diterbitkan otomatis tiap
 * periode (SPP bulanan, uang makan, asrama, dll). Tagihan nyata di-generate
 * per santri oleh TagihanRutinService + command keuangan:terbitkan-tagihan-rutin.
 */
class TagihanRutin extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'tagihan_rutin';

    public const TARGET = ['semua', 'lembaga', 'kelas', 'tingkat'];

    public const FREKUENSI = ['bulanan', 'semester', 'tahunan'];

    protected $fillable = [
        'tenant_id', 'nama', 'jenis_tagihan', 'frekuensi', 'nominal',
        'target_tipe', 'target_lembaga_id', 'target_kelas_id', 'target_tingkat',
        'tanggal_terbit', 'tempo_hari', 'sumber_dana_id', 'divisi_unit_id',
        'keterangan', 'notif_wa', 'aktif', 'terakhir_terbit_periode',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal_terbit' => 'integer',
        'tempo_hari' => 'integer',
        'notif_wa' => 'boolean',
        'aktif' => 'boolean',
    ];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'target_lembaga_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'target_kelas_id');
    }

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }

    public function divisiUnit(): BelongsTo
    {
        return $this->belongsTo(DivisiUnit::class, 'divisi_unit_id');
    }
}
