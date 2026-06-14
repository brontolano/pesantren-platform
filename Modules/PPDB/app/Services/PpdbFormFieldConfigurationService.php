<?php

namespace Modules\PPDB\Services;

use App\Models\Setting;
use InvalidArgumentException;
use Modules\PPDB\Support\PpdbBiodataFields;
use RuntimeException;

/**
 * Konfigurasi field Biodata SPMB per tenant. Metadata field (label, section,
 * type, options, rules) berasal dari PpdbBiodataFields; service ini hanya
 * meng-override MODE tiap field per pesantren (disimpan di Setting `ppdb_form_fields`).
 *
 * Mode: required | optional | hidden | staf
 *   - staf = diisi panitia, DISEMBUNYIKAN dari form peserta.
 */
class PpdbFormFieldConfigurationService
{
    public const SETTING_KEY = 'ppdb_form_fields';

    public const MODES = ['required', 'optional', 'hidden', 'staf'];

    /**
     * Definisi field tergabung: metadata baku + mode override per tenant.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getConfiguration(): array
    {
        $stored = $this->storedModes();
        $defs = PpdbBiodataFields::all();

        foreach ($defs as $key => $def) {
            $mode = $stored[$key] ?? $def['mode'];
            $defs[$key]['mode'] = in_array($mode, self::MODES, true) ? $mode : $def['mode'];
        }

        return $defs;
    }

    /**
     * Simpan override mode per field. Hanya menerima key & mode yang valid;
     * key tak dikenal ditolak (cegah typo). Field tak disebut → pakai default.
     *
     * @param  array<string, mixed>  $configuration  [key => mode]
     */
    public function setConfiguration(array $configuration): Setting
    {
        $this->ensureTenantContext();

        $valid = PpdbBiodataFields::keys();
        $modes = [];

        foreach ($configuration as $key => $mode) {
            if (! in_array($key, $valid, true)) {
                throw new InvalidArgumentException("Field PPDB tidak dikenal: {$key}");
            }
            if (is_array($mode)) {
                $mode = $mode['mode'] ?? null;
            }
            if (! is_string($mode) || ! in_array($mode, self::MODES, true)) {
                throw new InvalidArgumentException("Mode field PPDB untuk `{$key}` tidak valid.");
            }
            $modes[$key] = $mode;
        }

        return Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => ['fields' => $modes]],
        );
    }

    /**
     * Konfigurasi default (mode baku per field) tanpa override tenant.
     *
     * @return array<string, array<string, mixed>>
     */
    public function defaultConfiguration(): array
    {
        return PpdbBiodataFields::all();
    }

    /**
     * Ambil override mode tersimpan: [key => mode]. Key tak dikenal diabaikan
     * (toleran terhadap config lama setelah daftar field berubah).
     *
     * @return array<string, string>
     */
    private function storedModes(): array
    {
        $setting = Setting::query()->where('key', self::SETTING_KEY)->first();
        $stored = $setting?->value;
        $fields = is_array($stored) ? ($stored['fields'] ?? $stored) : [];

        if (! is_array($fields)) {
            return [];
        }

        $valid = PpdbBiodataFields::keys();
        $modes = [];

        foreach ($fields as $key => $mode) {
            if (! in_array($key, $valid, true)) {
                continue; // abaikan key lama (mis. program_diminati, catatan_tambahan)
            }
            if (is_array($mode)) {
                $mode = $mode['mode'] ?? null;
            }
            if (is_string($mode) && in_array($mode, self::MODES, true)) {
                $modes[$key] = $mode;
            }
        }

        return $modes;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk konfigurasi field PPDB.');
        }
    }
}
