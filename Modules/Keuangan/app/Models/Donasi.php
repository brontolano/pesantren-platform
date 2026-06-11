<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Modules\Ledger\Models\Jurnal;

class Donasi extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUS_MENUNGGU = 'menunggu_transfer';

    public const STATUS_DIKONFIRMASI = 'dikonfirmasi';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'donasi';

    protected $fillable = [
        'tenant_id',
        'program_donasi_id',
        'nama_donatur',
        'kontak',
        'nominal',
        'pesan',
        'bukti_path',
        'status',
        'jurnal_id',
        'dikonfirmasi_oleh',
        'dikonfirmasi_at',
        'nomor_kuitansi',
        'nomor_kuitansi_urut',
        'client_ref',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'dikonfirmasi_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramDonasi::class, 'program_donasi_id');
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class, 'jurnal_id');
    }

    public function konfirmator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikonfirmasi_oleh');
    }
}
