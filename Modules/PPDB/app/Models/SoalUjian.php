<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Bank soal ujian PPDB. Auto-grading untuk pilihan ganda; essay dinilai manual.
 */
class SoalUjian extends Model
{
    use BelongsToTenant;

    public const TIPE_PG = 'pg';

    public const TIPE_ESSAY = 'essay';

    public const TIPE_LABELS = [
        self::TIPE_PG => 'Pilihan Ganda',
        self::TIPE_ESSAY => 'Essay',
    ];

    protected $table = 'soal_ujian';

    protected $fillable = [
        'tenant_id',
        'kategori_ujian',
        'pertanyaan',
        'tipe',
        'pilihan',
        'kunci_jawaban',
        'bobot',
        'aktif',
    ];

    protected $casts = [
        'pilihan' => 'array',
        'bobot' => 'integer',
        'aktif' => 'boolean',
    ];

    public function isPg(): bool
    {
        return $this->tipe === self::TIPE_PG;
    }

    public function isEssay(): bool
    {
        return $this->tipe === self::TIPE_ESSAY;
    }

    /**
     * Nilai otomatis untuk satu jawaban.
     * - PG  : `bobot` bila jawaban == kunci (abaikan kapital/spasi), selain itu 0.
     * - Essay: null (harus dinilai manual oleh penguji).
     */
    public function nilaiUntukJawaban(?string $jawaban): ?int
    {
        if ($this->isEssay()) {
            return null;
        }

        $kunci = strtoupper(trim((string) $this->kunci_jawaban));
        $jwb = strtoupper(trim((string) $jawaban));

        if ($kunci === '') {
            return 0;
        }

        return $jwb === $kunci ? (int) $this->bobot : 0;
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
