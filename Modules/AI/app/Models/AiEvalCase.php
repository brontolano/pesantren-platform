<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A024 — satu kasus eval gate rilis (tabel CENTRAL).
 *
 * ====================== PERINGATAN ATURAN EMAS #8 ======================
 * Kasus suite 'syariah' HANYA boleh lahir dari impor berkas seed yang
 * sudah/akan ditinjau ustadz (kasus-eval-syariah.json, GATE-3) atau dari
 * kasus PERILAKU teknis-linguistik netral eksisting (policy_cases.json).
 * DILARANG KERAS menambah kasus fikih/keagamaan baru dari kode — kolom
 * `sumber` (kurasi_ustadz|teknis|prd_verbatim) wajib jujur soal asalnya.
 * =======================================================================
 */
class AiEvalCase extends Model
{
    public const SUITES = ['syariah', 'faktual', 'injection'];

    public const SUMBER_KURASI_USTADZ = 'kurasi_ustadz';

    public const SUMBER_TEKNIS = 'teknis';

    public const SUMBER_PRD_VERBATIM = 'prd_verbatim';

    // Jenis harapan yang dipahami EvalRunner.
    public const JENIS_DIALIHKAN = 'dialihkan';

    public const JENIS_DIJAWAB_DARI_RUJUKAN = 'dijawab_dari_rujukan';

    public const JENIS_DITOLAK_TANPA_RUJUKAN = 'ditolak_tanpa_rujukan';

    public const JENIS_DIBLOKIR_POLICY = 'diblokir_policy';

    public const JENIS_LOLOS = 'lolos';

    public const JENIS_ROUTE_INTENT = 'route_intent';

    protected $table = 'ai_eval_case';

    protected $fillable = [
        'suite',
        'kode',
        'kanal',
        'input',
        'harapan',
        'sumber',
        'aktif',
    ];

    protected $casts = [
        'input' => 'array',
        'harapan' => 'array',
        'aktif' => 'boolean',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
