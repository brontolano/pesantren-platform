<?php

namespace Modules\Akademik\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

class NilaiService
{
    /**
     * @throws ValidationException
     */
    public function simpan(array $data, ?User $actor = null, ?Nilai $nilai = null): Nilai
    {
        $this->ensureTenantContext();

        Validator::make($data, [
            'santri_id' => ['required', 'integer'],
            'pelajaran_id' => ['required', 'integer'],
            'periode_akademik_id' => ['required', 'integer'],
            'kelas_id' => ['required', 'integer'],
            'nilai_harian' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nilai_uts' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nilai_uas' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nilai_akhir' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string'],
        ])->validate();

        $santri = Santri::query()->findOrFail($data['santri_id']);
        $pelajaran = Pelajaran::query()->findOrFail($data['pelajaran_id']);

        if ($santri->kelas_id !== (int) $data['kelas_id']) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas tidak sesuai dengan penempatan santri saat ini.',
            ]);
        }

        $this->assertActorCanInputNilai($actor, $pelajaran, (int) $data['kelas_id']);

        $payload = $data;
        $payload['nilai_akhir'] = $payload['nilai_akhir'] ?? $this->hitungNilaiAkhir($payload);
        $payload['input_by'] = $actor?->getKey();

        if ($nilai instanceof Nilai) {
            $nilai->fill($payload);
            $nilai->save();

            return $nilai->fresh();
        }

        return Nilai::query()->updateOrCreate([
            'santri_id' => $data['santri_id'],
            'pelajaran_id' => $data['pelajaran_id'],
            'periode_akademik_id' => $data['periode_akademik_id'],
        ], $payload);
    }

    public function hitungNilaiAkhir(array $nilai): float
    {
        $weights = $this->getGradeWeights();

        $harian = (float) ($nilai['nilai_harian'] ?? 0);
        $uts = (float) ($nilai['nilai_uts'] ?? 0);
        $uas = (float) ($nilai['nilai_uas'] ?? 0);

        $total = ($harian * $weights['harian']) + ($uts * $weights['uts']) + ($uas * $weights['uas']);

        return round($total / 100, 2);
    }

    /**
     * @return array{harian:int,uts:int,uas:int}
     */
    public function getGradeWeights(): array
    {
        $value = Setting::getTenantValue('akademik_grade_weights', null);

        if (is_array($value)) {
            $harian = (int) ($value['harian'] ?? 30);
            $uts = (int) ($value['uts'] ?? 30);
            $uas = (int) ($value['uas'] ?? 40);

            if ($harian + $uts + $uas === 100) {
                return compact('harian', 'uts', 'uas');
            }
        }

        return [
            'harian' => 30,
            'uts' => 30,
            'uas' => 40,
        ];
    }

    private function assertActorCanInputNilai(?User $actor, Pelajaran $pelajaran, int $kelasId): void
    {
        if (! $actor instanceof User) {
            return;
        }

        if ($actor->hasAnyRole(['super_admin', 'admin_pesantren', 'tu', 'kepala'])) {
            return;
        }

        if (! $actor->hasAnyRole(['guru', 'guru_tahfidz'])) {
            throw ValidationException::withMessages([
                'pelajaran_id' => 'User ini tidak diizinkan input nilai.',
            ]);
        }

        $hasSubject = (int) $pelajaran->guru_pengampu_id === (int) $actor->getKey();
        $hasSchedule = JadwalPelajaran::query()
            ->where('kelas_id', $kelasId)
            ->where('pelajaran_id', $pelajaran->id)
            ->exists();

        if (! $hasSubject || ! $hasSchedule) {
            throw ValidationException::withMessages([
                'pelajaran_id' => 'Guru hanya boleh input nilai untuk pelajaran dan kelas yang diampu.',
            ]);
        }
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk input nilai.');
        }
    }
}
