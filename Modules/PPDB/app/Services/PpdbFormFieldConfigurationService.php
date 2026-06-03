<?php

namespace Modules\PPDB\Services;

use App\Models\Setting;
use InvalidArgumentException;
use RuntimeException;

class PpdbFormFieldConfigurationService
{
    public const SETTING_KEY = 'ppdb_form_fields';

    /**
     * @return array<string, array{label: string, mode: string}>
     */
    public function getConfiguration(): array
    {
        $setting = Setting::query()
            ->where('key', self::SETTING_KEY)
            ->first();

        $stored = $setting?->value;
        $fields = is_array($stored) ? ($stored['fields'] ?? $stored) : [];

        return $this->normaliseConfiguration(is_array($fields) ? $fields : []);
    }

    public function setConfiguration(array $configuration): Setting
    {
        $this->ensureTenantContext();

        $normalised = $this->normaliseConfiguration($configuration);

        return Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => [
                    'fields' => collect($normalised)
                        ->mapWithKeys(fn (array $field, string $key): array => [$key => $field['mode']])
                        ->all(),
                ],
            ]
        );
    }

    /**
     * @return array<string, array{label: string, mode: string}>
     */
    public function defaultConfiguration(): array
    {
        return [
            'nama' => ['label' => 'Nama lengkap calon santri', 'mode' => 'required'],
            'nik' => ['label' => 'NIK / No. identitas', 'mode' => 'optional'],
            'tempat_lahir' => ['label' => 'Tempat lahir', 'mode' => 'optional'],
            'tanggal_lahir' => ['label' => 'Tanggal lahir', 'mode' => 'required'],
            'jenis_kelamin' => ['label' => 'Jenis kelamin', 'mode' => 'required'],
            'jenjang_asal' => ['label' => 'Jenjang sekolah asal', 'mode' => 'required'],
            'sekolah_asal' => ['label' => 'Sekolah asal', 'mode' => 'required'],
            'nama_wali' => ['label' => 'Nama wali', 'mode' => 'required'],
            'hubungan_wali' => ['label' => 'Hubungan dengan calon', 'mode' => 'optional'],
            'no_hp_wali' => ['label' => 'No. WhatsApp wali', 'mode' => 'required'],
            'email_wali' => ['label' => 'Email wali', 'mode' => 'optional'],
            'program_diminati' => ['label' => 'Program diminati', 'mode' => 'required'],
            'catatan_tambahan' => ['label' => 'Catatan tambahan', 'mode' => 'optional'],
        ];
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk konfigurasi field PPDB.');
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, array{label: string, mode: string}>
     */
    private function normaliseConfiguration(array $configuration): array
    {
        $defaults = $this->defaultConfiguration();
        $normalised = [];

        foreach ($defaults as $field => $meta) {
            $mode = $configuration[$field] ?? $meta['mode'];

            if (is_array($mode)) {
                $mode = $mode['mode'] ?? $meta['mode'];
            }

            if (! is_string($mode) || ! in_array($mode, ['required', 'optional', 'hidden'], true)) {
                throw new InvalidArgumentException("Mode field PPDB untuk `{$field}` tidak valid.");
            }

            $normalised[$field] = [
                'label' => $meta['label'],
                'mode' => $mode,
            ];
        }

        $unknownFields = array_diff(array_keys($configuration), array_keys($defaults));

        if ($unknownFields !== []) {
            throw new InvalidArgumentException(
                'Field PPDB tidak dikenal: '.implode(', ', $unknownFields)
            );
        }

        return $normalised;
    }
}
