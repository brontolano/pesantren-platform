<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\KebijakanRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * WP-34 — Lapisan tulis kebijakan pesantren: simpan/reset nilai Setting
 * untuk key terdaftar di KebijakanRegistry + audit trail (key, dari→ke, user)
 * + ekspor/impor JSON + penerapan preset. TIDAK menyentuh engine Setting.
 */
class KebijakanService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Nilai efektif: override tenant bila ada, selain itu default registry. */
    public function nilai(string $key): mixed
    {
        $entry = $this->entry($key);

        return Setting::getTenantValue($key, $entry['default']);
    }

    /** Apakah tenant punya override tersimpan untuk key ini? */
    public function adaOverride(string $key): bool
    {
        return Setting::query()
            ->where('tenant_id', tenant('id'))
            ->where('key', $key)
            ->exists();
    }

    /** Simpan nilai baru (idempoten — tak ada audit bila nilai tak berubah). */
    public function set(string $key, mixed $nilai, ?User $user = null): void
    {
        $entry = $this->entry($key);
        $nilai = $this->validasi($key, $entry, $nilai);
        $lama = $this->nilai($key);

        if ($lama === $nilai) {
            return;
        }

        Setting::updateOrCreate(
            ['tenant_id' => tenant('id'), 'key' => $key],
            ['value' => $nilai],
        );

        $this->audit->log('kebijakan.diubah', $user, null, [
            'key' => $key, 'dari' => $lama, 'ke' => $nilai,
        ]);
    }

    /** Kembalikan ke bawaan: hapus baris override + audit. */
    public function reset(string $key, ?User $user = null): void
    {
        $entry = $this->entry($key);

        $deleted = Setting::query()
            ->where('tenant_id', tenant('id'))
            ->where('key', $key)
            ->delete();

        if ($deleted > 0) {
            $this->audit->log('kebijakan.direset', $user, null, [
                'key' => $key, 'ke' => $entry['default'],
            ]);
        }
    }

    /** Reset seluruh key dalam satu kelompok. */
    public function resetKelompok(string $kelompok, ?User $user = null): void
    {
        foreach (KebijakanRegistry::entries() as $key => $entry) {
            if ($entry['kelompok'] === $kelompok) {
                $this->reset($key, $user);
            }
        }
    }

    /**
     * Ekspor semua OVERRIDE tenant (hanya key terdaftar) sebagai array siap-JSON.
     *
     * @return array{versi:int, diekspor_pada:string, kebijakan:array<string,mixed>}
     */
    public function ekspor(): array
    {
        $overrides = Setting::query()
            ->where('tenant_id', tenant('id'))
            ->whereIn('key', KebijakanRegistry::keys())
            ->pluck('value', 'key')
            ->all();

        return [
            'versi' => 1,
            'diekspor_pada' => now()->toIso8601String(),
            'kebijakan' => $overrides,
        ];
    }

    /**
     * Pratinjau impor: diff per key (lama → baru). Key tak terdaftar diabaikan,
     * nilai yang gagal validasi tipe/rentang DITOLAK per-key (dilaporkan
     * terpisah dengan alasan) — key valid lainnya tetap masuk diff.
     *
     * @param  array<string, mixed>  $payload  hasil json_decode berkas ekspor
     * @return array{diff: array<int, array{key:string, label:string, dari:mixed, ke:mixed}>, diabaikan: array<int, string>, ditolak: array<int, array{key:string, label:string, nilai:mixed, alasan:string}>}
     */
    public function pratinjauImpor(array $payload): array
    {
        $kebijakan = $payload['kebijakan'] ?? null;
        if (! is_array($kebijakan)) {
            throw new InvalidArgumentException('Berkas tidak valid: bagian "kebijakan" tidak ditemukan.');
        }

        $entries = KebijakanRegistry::entries();
        $diff = [];
        $diabaikan = [];
        $ditolak = [];

        foreach ($kebijakan as $key => $baru) {
            if (! isset($entries[$key])) {
                $diabaikan[] = (string) $key;

                continue;
            }

            try {
                $baru = $this->validasi($key, $entries[$key], $baru);
            } catch (InvalidArgumentException $e) {
                $ditolak[] = [
                    'key' => $key, 'label' => $entries[$key]['label'],
                    'nilai' => $baru, 'alasan' => $e->getMessage(),
                ];

                continue;
            }

            $lama = $this->nilai($key);
            if ($lama !== $baru) {
                $diff[] = ['key' => $key, 'label' => $entries[$key]['label'], 'dari' => $lama, 'ke' => $baru];
            }
        }

        return ['diff' => $diff, 'diabaikan' => $diabaikan, 'ditolak' => $ditolak];
    }

    /**
     * Terapkan hasil impor (set per key → audit per perubahan). Key dengan
     * nilai tak valid sudah disaring di pratinjau (ditolak, dilewati di sini).
     *
     * @param  array<string, mixed>  $payload
     * @return int jumlah key yang berubah
     */
    public function terapkanImpor(array $payload, ?User $user = null): int
    {
        $pratinjau = $this->pratinjauImpor($payload);

        DB::transaction(function () use ($pratinjau, $user): void {
            foreach ($pratinjau['diff'] as $row) {
                $this->set($row['key'], $row['ke'], $user);
            }
        });

        return count($pratinjau['diff']);
    }

    /** Terapkan preset bawaan (salafiyah-kecil | modern-multi-lembaga). */
    public function terapkanPreset(string $nama, ?User $user = null): int
    {
        $preset = KebijakanRegistry::presets()[$nama] ?? null;
        if ($preset === null) {
            throw new InvalidArgumentException("Preset tidak dikenal: {$nama}");
        }

        $berubah = 0;
        DB::transaction(function () use ($preset, $user, &$berubah): void {
            foreach ($preset['nilai'] as $key => $nilai) {
                if ($this->nilai($key) !== $nilai) {
                    $this->set($key, $nilai, $user);
                    $berubah++;
                }
            }
        });

        $this->audit->log('kebijakan.preset_diterapkan', $user, null, [
            'preset' => $nama, 'jumlah_berubah' => $berubah,
        ]);

        return $berubah;
    }

    /**
     * Validasi + normalisasi nilai terhadap tipe (dan min/max) entry registry.
     * Nilai tak valid → InvalidArgumentException dengan pesan jelas.
     */
    private function validasi(string $key, array $entry, mixed $nilai): mixed
    {
        $label = $entry['label'];

        switch ($entry['tipe']) {
            case 'bool':
                $bool = filter_var($nilai, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    throw new InvalidArgumentException(
                        "Nilai untuk \"{$label}\" ({$key}) harus ya/tidak (true/false), bukan: ".$this->ringkas($nilai)
                    );
                }

                return $bool;

            case 'number':
                if (! is_numeric($nilai)) {
                    throw new InvalidArgumentException(
                        "Nilai untuk \"{$label}\" ({$key}) harus angka, bukan: ".$this->ringkas($nilai)
                    );
                }
                $angka = $nilai + 0;
                if (isset($entry['min']) && $angka < $entry['min']) {
                    throw new InvalidArgumentException(
                        "Nilai untuk \"{$label}\" ({$key}) minimal {$entry['min']}, diberikan: {$angka}"
                    );
                }
                if (isset($entry['max']) && $angka > $entry['max']) {
                    throw new InvalidArgumentException(
                        "Nilai untuk \"{$label}\" ({$key}) maksimal {$entry['max']}, diberikan: {$angka}"
                    );
                }

                return $angka;

            case 'select':
                $options = $entry['options'] ?? [];
                if (! (is_string($nilai) || is_int($nilai)) || ! array_key_exists($nilai, $options)) {
                    throw new InvalidArgumentException(
                        "Nilai untuk \"{$label}\" ({$key}) harus salah satu dari: ".implode(', ', array_keys($options)).' — bukan: '.$this->ringkas($nilai)
                    );
                }

                return $nilai;

            case 'json':
                if (is_array($nilai)) {
                    return $nilai;
                }
                if (is_string($nilai)) {
                    $decoded = json_decode($nilai, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }

                throw new InvalidArgumentException(
                    "Nilai untuk \"{$label}\" ({$key}) harus JSON valid (array/objek), bukan: ".$this->ringkas($nilai)
                );

            default: // text
                if ($nilai !== null && ! is_scalar($nilai)) {
                    throw new InvalidArgumentException(
                        "Nilai untuk \"{$label}\" ({$key}) harus teks, bukan: ".$this->ringkas($nilai)
                    );
                }

                return (string) $nilai;
        }
    }

    /** Representasi singkat nilai untuk pesan error. */
    private function ringkas(mixed $nilai): string
    {
        $json = json_encode($nilai, JSON_UNESCAPED_UNICODE);

        return mb_strimwidth($json === false ? get_debug_type($nilai) : $json, 0, 80, '…');
    }

    /** @return array{label:string, deskripsi:string, default:mixed, kelompok:string, tipe:string, options?:array<string,string>, min?:int|float, max?:int|float} */
    private function entry(string $key): array
    {
        $entry = KebijakanRegistry::entries()[$key] ?? null;
        if ($entry === null) {
            throw new InvalidArgumentException("Key kebijakan tidak terdaftar: {$key}");
        }

        return $entry;
    }
}
