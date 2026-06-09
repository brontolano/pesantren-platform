<?php

namespace Modules\Pengadaan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Approvable;

class Belanja extends Model
{
    use Approvable, BelongsToTenant, SoftDeletes;

    protected $table = 'belanja';

    protected $fillable = [
        'tenant_id', 'client_ref', 'tanggal', 'uraian', 'jenis', 'kategori_rkam',
        'divisi_id', 'sumber_dana_id', 'dpp', 'vendor_npwp',
        'ppn_dipungut', 'pph_jenis', 'pph_dipungut', 'total',
        'akun_beban_id', 'akun_kas_id', 'jurnal_id',
        'status_approval', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'vendor_npwp' => 'boolean',
        'dpp' => 'decimal:2',
        'ppn_dipungut' => 'decimal:2',
        'pph_dipungut' => 'decimal:2',
        'total' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
