<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Database\Factories\KomponenNilaiFactory;

/**
 * Komponen nilai konfigurabel. pelajaran_id NULL = default tenant.
 */
class KomponenNilai extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'komponen_nilai';

    protected $fillable = [
        'tenant_id',
        'pelajaran_id',
        'nama',
        'bobot',
        'urutan',
    ];

    protected $casts = [
        'bobot' => 'integer',
        'urutan' => 'integer',
    ];

    protected static function newFactory(): KomponenNilaiFactory
    {
        return KomponenNilaiFactory::new();
    }

    public function pelajaran(): BelongsTo
    {
        return $this->belongsTo(Pelajaran::class, 'pelajaran_id');
    }
}
