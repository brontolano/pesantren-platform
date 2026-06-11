<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;
use Modules\Payroll\Observers\EmployeeObserver;

/**
 * G2-10 — Master Pegawai/Employee. Tertaut user (untuk slip gaji) + struktur
 * gaji konfigurabel (komponen_gaji). WP-03: observer memindahkan role
 * turunan penugasan saat users_id diganti.
 */
#[ObservedBy(EmployeeObserver::class)]
class Employee extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'employee';

    protected $fillable = [
        'tenant_id', 'users_id', 'nip', 'nama', 'jabatan', 'status_kepegawaian',
        'npwp', 'ptkp_status', 'tanggal_masuk', 'aktif',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'aktif' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function komponenGaji(): HasMany
    {
        return $this->hasMany(KomponenGaji::class, 'employee_id');
    }

    /**
     * WP-03 — Riwayat penugasan multi-peran (pengganti kolom `jabatan` string
     * yang deprecated). Aktif = tanggal_selesai NULL.
     */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'employee_id');
    }

    /**
     * Jabatan aktif untuk tampilan: prioritas penugasan is_primary aktif,
     * fallback penugasan aktif termuda. NULL bila belum ada penugasan.
     */
    public function jabatanAktif(): ?Jabatan
    {
        $penugasan = $this->penugasan()
            ->whereNull('tanggal_selesai')
            ->orderByDesc('is_primary')
            ->orderByDesc('tanggal_mulai')
            ->with('jabatan')
            ->first();

        return $penugasan?->jabatan;
    }

    /**
     * Resolusi struktur gaji → honor_pokok (Σtetap), tunjangan (Σtunjangan),
     * potongan (Σpotongan). Hanya komponen aktif.
     *
     * @return array{honor_pokok:float, tunjangan:float, potongan:float}
     */
    public function strukturGaji(): array
    {
        $komponen = $this->relationLoaded('komponenGaji') ? $this->komponenGaji : $this->komponenGaji()->get();
        $aktif = $komponen->where('aktif', true);

        return [
            'honor_pokok' => round((float) $aktif->where('tipe', 'tetap')->sum('nominal'), 2),
            'tunjangan' => round((float) $aktif->where('tipe', 'tunjangan')->sum('nominal'), 2),
            'potongan' => round((float) $aktif->where('tipe', 'potongan')->sum('nominal'), 2),
        ];
    }
}
