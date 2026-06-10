<?php

namespace Modules\PPDB\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/** Butir soal (PG berkunci / essay) milik satu paket. */
class Soal extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $table = 'soal';

    protected $fillable = ['paket_soal_id', 'tipe', 'pertanyaan', 'pilihan', 'kunci', 'bobot', 'sort'];

    protected $casts = [
        'pilihan' => 'array',
        'bobot' => 'integer',
        'sort' => 'integer',
    ];

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_id');
    }
}
