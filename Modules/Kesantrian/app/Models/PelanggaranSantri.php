<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * WP-09 — Catatan pelanggaran/prestasi santri.
 *
 * Alur status: dicatat → diverifikasi → sanksi_dijalankan → selesai
 * (atau dibatalkan dengan alasan). Poin HANYA berlaku pada status
 * diverifikasi ke atas (lihat STATUS_POIN_BERLAKU) — gerbang verifikasi
 * mencegah poin jadi alat bully pencatat berlapis (qism keamanan santri).
 */
class PelanggaranSantri extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'pelanggaran_santri';

    public const STATUS = ['dicatat', 'diverifikasi', 'sanksi_dijalankan', 'selesai', 'dibatalkan'];

    /** Status yang poinnya terhitung dalam akumulasi. */
    public const STATUS_POIN_BERLAKU = ['diverifikasi', 'sanksi_dijalankan', 'selesai'];

    protected $fillable = [
        'tenant_id', 'santri_id', 'jenis_pelanggaran_id', 'waktu_kejadian', 'lokasi',
        'kronologi', 'foto_bukti', 'pencatat_id', 'status', 'verifikator_id',
        'verified_at', 'alasan_batal',
    ];

    protected $casts = [
        'waktu_kejadian' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisPelanggaran::class, 'jenis_pelanggaran_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    public function sanksi(): HasMany
    {
        return $this->hasMany(SanksiSantri::class, 'pelanggaran_id');
    }

    /** URL foto bukti via /_tenant-storage (Storage::url 404 di Docker). */
    public function fotoBuktiUrl(): ?string
    {
        if (! $this->foto_bukti) {
            return null;
        }

        return url('/_tenant-storage/'.ltrim($this->foto_bukti, '/'));
    }
}
