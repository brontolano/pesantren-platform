<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenMutu extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'dokumen_mutu';

    protected $fillable = [
        'tenant_id', 'judul', 'jenis', 'regulasi_acuan', 'berkas_url', 'status_kelengkapan', 'created_by',
    ];
}
