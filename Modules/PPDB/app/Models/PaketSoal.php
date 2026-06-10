<?php

namespace Modules\PPDB\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/** Paket soal ujian PPDB (Bank Soal). */
class PaketSoal extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $table = 'paket_soal';

    protected $fillable = ['nama', 'kategori_ujian', 'durasi_menit', 'jumlah_tampil', 'is_active'];

    protected $casts = [
        'durasi_menit' => 'integer',
        'jumlah_tampil' => 'integer',
        'is_active' => 'boolean',
    ];

    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class)->orderBy('sort');
    }
}
