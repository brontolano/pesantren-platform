<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * WP-19 — Pelamar rekrutmen ringan.
 *
 * Pipeline: lamaran → tes_baca_kitab → microteaching → wawancara →
 * diterima | ditolak. Transisi divalidasi RekrutmenService (maju satu tahap,
 * atau ditolak dari tahap mana pun yang belum final). Pelamar ditolak
 * TERARSIP (baris tetap, query-able via scopeDitolak/filter tahap).
 * employee_id = penanda sudah dikonversi jadi pegawai (idempoten).
 */
class Pelamar extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'pelamar';

    /** Urutan pipeline seleksi (maju satu-satu). */
    public const URUTAN_TAHAP = ['lamaran', 'tes_baca_kitab', 'microteaching', 'wawancara', 'diterima'];

    public const TAHAP = [
        'lamaran' => 'Lamaran Masuk',
        'tes_baca_kitab' => 'Tes Baca Kitab',
        'microteaching' => 'Microteaching',
        'wawancara' => 'Wawancara',
        'diterima' => 'Diterima',
        'ditolak' => 'Ditolak',
    ];

    /** Tahap final — tidak ada transisi keluar. */
    public const TAHAP_FINAL = ['diterima', 'ditolak'];

    protected $fillable = [
        'tenant_id', 'nama', 'kontak', 'asal', 'is_alumni', 'berkas_path',
        'tahap', 'catatan_penilai', 'employee_id',
    ];

    protected $casts = [
        'is_alumni' => 'boolean',
        'catatan_penilai' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** Tahap tujuan yang sah dari tahap saat ini. */
    public function tahapTujuanValid(): array
    {
        if (in_array($this->tahap, self::TAHAP_FINAL, true)) {
            return [];
        }

        $idx = array_search($this->tahap, self::URUTAN_TAHAP, true);
        $tujuan = [];
        if ($idx !== false && isset(self::URUTAN_TAHAP[$idx + 1])) {
            $tujuan[] = self::URUTAN_TAHAP[$idx + 1];
        }
        $tujuan[] = 'ditolak';

        return $tujuan;
    }

    public function sudahDikonversi(): bool
    {
        return $this->employee_id !== null;
    }

    public function scopeDitolak($query)
    {
        return $query->where('tahap', 'ditolak');
    }
}
