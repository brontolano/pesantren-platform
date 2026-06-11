<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A024 — laporan satu eksekusi `ai:eval` (tabel CENTRAL, append-only).
 *
 * Gate rilis dibaca dari sini (Blueprint K7: gate eval ≠ CI): lulus_gate
 * false = fitur terkait TIDAK dirilis, tanpa pengecualian. Kolom model +
 * prompt_versi merekam konfigurasi yang dievaluasi — ganti model/prompt
 * berarti WAJIB eval ulang (bandingkan kolom antar run).
 */
class AiEvalRun extends Model
{
    protected $table = 'ai_eval_run';

    /** Run tidak pernah dimutasi — created_at diisi manual saat insert. */
    public $timestamps = false;

    protected $fillable = [
        'suite',
        'total',
        'lulus',
        'gagal',
        'skor',
        'ambang',
        'lulus_gate',
        'model',
        'prompt_versi',
        'detail_gagal',
        'durasi_ms',
        'tenant_id',
        'dijalankan_oleh',
        'created_at',
    ];

    protected $casts = [
        'total' => 'integer',
        'lulus' => 'integer',
        'gagal' => 'integer',
        'skor' => 'decimal:4',
        'ambang' => 'decimal:4',
        'lulus_gate' => 'boolean',
        'prompt_versi' => 'array',
        'detail_gagal' => 'array',
        'durasi_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
