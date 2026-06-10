<?php

namespace Modules\PPDB\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/** Attempt ujian online satu calon pada satu paket (timer server-side). */
class UjianAttempt extends Model
{
    use BelongsToTenant;

    protected $table = 'ujian_attempt';

    protected $fillable = [
        'calon_santri_id', 'paket_soal_id', 'soal_tampil', 'jawaban',
        'mulai_at', 'deadline_at', 'selesai_at',
        'nilai_pg', 'nilai_essay', 'nilai_final', 'status',
    ];

    protected $casts = [
        'soal_tampil' => 'array',
        'jawaban' => 'array',
        'mulai_at' => 'datetime',
        'deadline_at' => 'datetime',
        'selesai_at' => 'datetime',
        'nilai_pg' => 'decimal:2',
        'nilai_essay' => 'decimal:2',
        'nilai_final' => 'decimal:2',
    ];

    public function calonSantri(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class);
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_id');
    }
}
