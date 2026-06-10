<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/** Surat masuk/keluar (administrasi TU) — lihat SuratService utk penomoran. */
class Surat extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $table = 'surat';

    protected $fillable = [
        'jenis', 'tahun', 'nomor_urut', 'nomor_surat', 'tanggal_surat',
        'pihak', 'perihal', 'lampiran_path', 'keterangan', 'created_by',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'nomor_urut' => 'integer',
        'tanggal_surat' => 'date',
    ];
}
