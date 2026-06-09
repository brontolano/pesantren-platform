<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * G2-10 — Master Pegawai/Employee. Tertaut user (untuk slip gaji) + struktur
 * gaji konfigurabel (komponen_gaji).
 */
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
