<?php

namespace Modules\PPDB\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Services\AsramaService;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\SantriService;
use Modules\PPDB\Models\CalonSantri;
use RuntimeException;

class PpdbReregistrationService
{
    private const ELIGIBLE_STATUSES = [
        'lulus',
        'cadangan',
        'registrasi_ulang',
    ];

    public function __construct(
        private readonly SantriService $santriService,
    ) {
    }

    public function register(CalonSantri|int $calonSantri, array $payload): CalonSantri
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($calonSantri, $payload): CalonSantri {
            $candidate = $this->resolveCandidate($calonSantri);

            $this->ensureCandidateCanReregister($candidate);

            $santriPayload = $this->buildSantriPayload($candidate, $payload);
            $santri = $candidate->santri_id === null
                ? $this->santriService->create($santriPayload)
                : $this->updateExistingSantri($candidate->santri_id, $santriPayload);

            $candidate->forceFill([
                'nama' => $santriPayload['nama'],
                'tanggal_lahir' => $santriPayload['tanggal_lahir'],
                'tempat_lahir' => $santriPayload['tempat_lahir'] ?? null,
                'jenis_kelamin' => $santriPayload['jenis_kelamin'],
                'nama_ayah' => $santriPayload['nama_ayah'] ?? null,
                'nama_ibu' => $santriPayload['nama_ibu'] ?? null,
                'no_hp_wali' => $santriPayload['no_hp_wali'] ?? null,
                'email_wali' => $santriPayload['email_wali'] ?? null,
                'alamat' => $santriPayload['alamat'] ?? null,
                'asal_sekolah' => $santriPayload['asal_sekolah'] ?? null,
                'status_pendaftaran' => 'registrasi_ulang',
                'santri_id' => $santri->id,
                'tanggal_registrasi_ulang' => now(),
            ])->save();

            // Penempatan asrama (opsional) saat daftar ulang. Lewat AsramaService
            // agar pagar kapasitas + isolasi tenant + penutupan penempatan lama
            // ditegakkan. Hanya dijalankan bila kamar dipilih (modul asrama aktif).
            $this->tempatkanAsrama($santri, $payload);

            // App Santri: konversi LULUS -> akun login santri + tautan akun wali.
            // Best-effort (tak menggagalkan daftar ulang bila pembuatan akun gagal).
            try {
                $akun = app(\Modules\Kesantrian\Services\SantriAccountService::class);
                $akun->provision($santri->fresh());
                $akun->tautkanWali($santri->fresh());
            } catch (\Throwable $e) {
                report($e);
            }

            return $candidate->fresh()->load('santri');
        });
    }

    /**
     * Tempatkan santri ke kamar asrama bila `kamar_asrama_id` disertakan.
     * No-op bila kosong (modul asrama tidak dipakai). Melempar bila kamar tak
     * ditemukan / penuh / lintas-tenant → daftar ulang gagal jelas (rollback).
     */
    private function tempatkanAsrama(Santri $santri, array $payload): void
    {
        $kamarId = $payload['kamar_asrama_id'] ?? null;
        if (empty($kamarId)) {
            return;
        }

        $kamar = KamarAsrama::query()->find($kamarId);
        if (! $kamar instanceof KamarAsrama) {
            throw new ModelNotFoundException('Kamar asrama tujuan tidak ditemukan untuk tenant ini.');
        }

        app(AsramaService::class)->assignSantriToKamar($santri, $kamar);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk registrasi ulang PPDB.');
        }
    }

    private function resolveCandidate(CalonSantri|int $calonSantri): CalonSantri
    {
        if ($calonSantri instanceof CalonSantri) {
            return CalonSantri::query()
                ->whereKey($calonSantri->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        return CalonSantri::query()
            ->whereKey($calonSantri)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureCandidateCanReregister(CalonSantri $candidate): void
    {
        if (! in_array($candidate->status_pendaftaran, self::ELIGIBLE_STATUSES, true)) {
            throw new InvalidArgumentException('Registrasi ulang hanya boleh untuk calon dengan status lulus atau cadangan.');
        }
    }

    private function buildSantriPayload(CalonSantri $candidate, array $payload): array
    {
        $tahunAngkatan = $this->normalizeAcademicYear($payload['tahun_angkatan'] ?? null);

        $data = [
            'tenant_id' => tenancy()->tenant->getTenantKey(),
            'nis' => $this->normalizeNis($payload['nis'] ?? null, $candidate),
            'nama' => $this->stringOrFallback($payload, 'nama', $candidate->nama, true),
            'tanggal_lahir' => $payload['tanggal_lahir'] ?? $candidate->tanggal_lahir?->toDateString(),
            'tempat_lahir' => $this->stringOrFallback($payload, 'tempat_lahir', $candidate->tempat_lahir),
            'jenis_kelamin' => $this->normalizeGender($payload['jenis_kelamin'] ?? $candidate->jenis_kelamin),
            'agama' => $this->stringOrFallback($payload, 'agama', 'Islam', true),
            'asal_sekolah' => $this->stringOrFallback($payload, 'asal_sekolah', $candidate->asal_sekolah),
            'alamat' => $this->stringOrFallback($payload, 'alamat', $candidate->alamat),
            'no_hp_wali' => $this->stringOrFallback($payload, 'no_hp_wali', $candidate->no_hp_wali),
            'email_wali' => $this->normalizeNullableEmail($payload['email_wali'] ?? $candidate->email_wali),
            'nama_ayah' => $this->stringOrFallback($payload, 'nama_ayah', $candidate->nama_ayah),
            'nama_ibu' => $this->stringOrFallback($payload, 'nama_ibu', $candidate->nama_ibu),
            'pekerjaan_ayah' => $this->stringOrFallback($payload, 'pekerjaan_ayah', null),
            'pekerjaan_ibu' => $this->stringOrFallback($payload, 'pekerjaan_ibu', null),
            'status' => 'aktif',
            'tahun_angkatan' => $tahunAngkatan,
            'kelas_id' => $payload['kelas_id'] ?? null,
            'photo_url' => $this->stringOrFallback($payload, 'photo_url', null),
            'wali_user_id' => $payload['wali_user_id'] ?? null,
        ];

        if ($data['tanggal_lahir'] === null || $data['tanggal_lahir'] === '') {
            throw new InvalidArgumentException('Tanggal lahir wajib ada saat registrasi ulang.');
        }

        return $data;
    }

    private function updateExistingSantri(int $santriId, array $payload): Santri
    {
        $santri = Santri::query()->find($santriId);

        if (! $santri instanceof Santri) {
            throw new ModelNotFoundException('Santri hasil registrasi ulang tidak ditemukan pada tenant aktif.');
        }

        return $this->santriService->update($santri, $payload);
    }

    private function normalizeAcademicYear(mixed $value): string
    {
        $normalized = trim((string) $value);

        if (! preg_match('/^\d{4}\/\d{4}$/', $normalized)) {
            throw new InvalidArgumentException('Tahun angkatan wajib berformat YYYY/YYYY, misalnya 2026/2027.');
        }

        return $normalized;
    }

    private function normalizeNis(mixed $nis, CalonSantri $candidate): string
    {
        $normalized = trim((string) $nis);

        if ($normalized !== '') {
            return $normalized;
        }

        if ($candidate->santri_id !== null) {
            $santri = Santri::query()->find($candidate->santri_id);
            if ($santri instanceof Santri) {
                return $santri->nis;
            }
        }

        $year = now()->format('Y');
        $prefix = 'S'.$year;
        $lastNis = Santri::query()
            ->where('nis', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('nis')
            ->value('nis');

        $nextSequence = 1;

        if (is_string($lastNis) && preg_match('/^S\d{4}(\d{4})$/', $lastNis, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeGender(mixed $gender): string
    {
        $normalized = strtoupper(trim((string) $gender));

        if (! in_array($normalized, ['L', 'P'], true)) {
            throw new InvalidArgumentException('Jenis kelamin registrasi ulang harus L atau P.');
        }

        return $normalized;
    }

    private function normalizeNullableEmail(mixed $email): ?string
    {
        $normalized = trim((string) $email);

        if ($normalized === '') {
            return null;
        }

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email wali registrasi ulang tidak valid.');
        }

        return $normalized;
    }

    private function stringOrFallback(array $payload, string $key, ?string $fallback, bool $required = false): ?string
    {
        $value = array_key_exists($key, $payload) ? $payload[$key] : $fallback;
        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '') {
            if ($required) {
                throw new InvalidArgumentException("Field {$key} wajib diisi saat registrasi ulang.");
            }

            return null;
        }

        return $normalized;
    }
}
