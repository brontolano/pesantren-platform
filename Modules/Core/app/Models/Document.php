<?php

namespace Modules\Core\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Dokumen/lampiran (F5). Disajikan via /_tenant-storage/{path}.
 */
class Document extends Model
{
    use BelongsToTenant;

    protected $table = 'document';

    protected $fillable = [
        'tenant_id', 'documentable_type', 'documentable_id', 'kategori', 'judul',
        'path', 'nama_asli', 'mime', 'ukuran', 'versi', 'is_dokumen_halal', 'uploaded_by',
    ];

    protected $casts = [
        'versi' => 'integer',
        'ukuran' => 'integer',
        'is_dokumen_halal' => 'boolean',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** URL serve file tenant (pola /_tenant-storage). */
    public function getUrlAttribute(): string
    {
        return '/_tenant-storage/'.ltrim((string) $this->path, '/');
    }
}
