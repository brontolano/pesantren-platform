<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/** WP-09 — Eksekusi sanksi atas satu pelanggaran terverifikasi. */
class SanksiSantri extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'sanksi_santri';

    protected $fillable = [
        'tenant_id', 'pelanggaran_id', 'jenis_sanksi', 'pelaksana_id', 'tanggal', 'bukti', 'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function pelanggaran(): BelongsTo
    {
        return $this->belongsTo(PelanggaranSantri::class, 'pelanggaran_id');
    }

    public function pelaksana(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelaksana_id');
    }
}
