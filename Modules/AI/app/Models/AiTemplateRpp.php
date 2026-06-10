<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * T-A041 — template RPP / modul ajar (tabel TENANT).
 *
 * Struktur dokumen sepenuhnya MILIK TENANT (Wakakurikulum membuat/mengubah).
 * Aturan Emas #11 + #8: tanpa taksonomi regulasi di kode — platform hanya
 * menyediakan satu template default generik netral (AiTemplateRppSeeder)
 * yang bisa diubah penuh.
 *
 * seksi = array {kunci, judul, petunjuk, wajib}:
 *  - kunci    : slug unik di dalam template (kunci join input_guru & isi draf);
 *  - petunjuk : instruksi pengisian untuk AI/guru; bila memuat kata "salin",
 *               seksi DISALIN dari isian guru tanpa LLM (RppGenerator).
 */
class AiTemplateRpp extends Model
{
    protected $table = 'ai_template_rpp';

    protected $fillable = [
        'nama',
        'jenis_lembaga',
        'seksi',
        'aktif',
        'dibuat_oleh',
    ];

    protected $casts = [
        'seksi' => 'array',
        'aktif' => 'boolean',
        'dibuat_oleh' => 'integer',
    ];

    public function draf(): HasMany
    {
        return $this->hasMany(AiDrafRpp::class, 'template_id');
    }
}
