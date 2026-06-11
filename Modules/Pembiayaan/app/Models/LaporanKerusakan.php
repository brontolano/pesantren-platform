<?php

namespace Modules\Pembiayaan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * WP-33 — Laporan kerusakan sarpras dari PWA Guru.
 * Siklus status SEARAH: masuk → dikerjakan → selesai (lihat SarprasService).
 * Foto diserve via /_tenant-storage/kerusakan/... (gated login).
 */
class LaporanKerusakan extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'laporan_kerusakan';

    public const STATUS = ['masuk', 'dikerjakan', 'selesai'];

    protected $fillable = [
        'tenant_id',
        'pelapor_id',
        'lokasi',
        'deskripsi',
        'foto_path',
        'status',
        'biaya',
        'catatan',
    ];

    protected $casts = [
        'biaya' => 'decimal:2',
    ];

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelapor_id');
    }

    public function fotoUrl(): ?string
    {
        return $this->foto_path ? '/_tenant-storage/'.ltrim($this->foto_path, '/') : null;
    }
}
