<?php

namespace App\Support;

use App\Models\Setting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;

/**
 * Custom Field per-tenant (native, tanpa plugin).
 *
 * Definisi field kustom per entitas disimpan sebagai Setting tenant JSON dengan
 * key `custom_fields.{entitas}` (mis. `custom_fields.santri`), meniru pola
 * `ppdb_form_fields` yang sudah ada. Nilai per-record disimpan di kolom JSON
 * `custom_fields` pada tabel entitas (mis. `santri.custom_fields`).
 *
 * Kontrak bentuk definisi (satu baris = satu field):
 *   [
 *     'key'      => 'golongan_darah',      // slug stabil, dipakai sbg kunci JSON
 *     'label'    => 'Golongan Darah',
 *     'type'     => 'text|number|date|select',
 *     'options'  => "A\nB\nAB\nO",         // hanya utk select; 1 opsi per baris
 *     'required' => false,
 *   ]
 *
 * Aman & idempoten:
 * - Tenant tanpa definisi → daftar kosong → seksi form tak menampilkan apa pun.
 * - Definisi hilang tak merusak nilai lama (nilai tetap di kolom JSON).
 * - Nilai untuk key yang tak lagi terdefinisi diabaikan saat render (tak error).
 */
class CustomFieldRegistry
{
    public const ENTITY_SANTRI = 'santri';

    public const TYPES = [
        'text' => 'Teks',
        'number' => 'Angka',
        'date' => 'Tanggal',
        'select' => 'Pilihan',
    ];

    /** Kolom JSON tempat nilai custom field disimpan di tabel entitas. */
    public const VALUE_COLUMN = 'custom_fields';

    protected static function settingKey(string $entity): string
    {
        return 'custom_fields.'.$entity;
    }

    /**
     * Daftar definisi field kustom untuk entitas (sudah dinormalisasi & valid).
     *
     * @return array<int, array{key:string,label:string,type:string,options:array<int,string>,required:bool}>
     */
    public static function definitions(string $entity): array
    {
        $raw = Setting::getTenantValue(static::settingKey($entity), []);
        $rows = is_array($raw) ? ($raw['fields'] ?? $raw) : [];

        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = static::slug((string) ($row['key'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $type = (string) ($row['type'] ?? 'text');

            // Buang baris tak valid / duplikat / tipe tak dikenal → tak merusak render.
            if ($key === '' || $label === '' || ! isset(static::TYPES[$type]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $out[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'options' => static::parseOptions($row['options'] ?? null),
                'required' => (bool) ($row['required'] ?? false),
            ];
        }

        return $out;
    }

    public static function hasDefinitions(string $entity): bool
    {
        return static::definitions($entity) !== [];
    }

    /**
     * Komponen form Filament untuk tiap field kustom (statePath: custom_fields.{key}).
     *
     * @return array<int, Component>
     */
    public static function formComponents(string $entity): array
    {
        $components = [];

        foreach (static::definitions($entity) as $def) {
            $path = static::VALUE_COLUMN.'.'.$def['key'];

            $component = match ($def['type']) {
                'number' => TextInput::make($path)->numeric(),
                'date' => DatePicker::make($path)->native(false),
                'select' => Select::make($path)
                    ->options(array_combine($def['options'], $def['options']) ?: []),
                default => TextInput::make($path)->maxLength(255),
            };

            $component = $component
                ->label($def['label'])
                ->required($def['required'])
                ->dehydrated();

            $components[] = $component;
        }

        return $components;
    }

    /**
     * Entri infolist untuk tiap field kustom.
     *
     * @return array<int, TextEntry>
     */
    public static function infolistEntries(string $entity): array
    {
        $entries = [];

        foreach (static::definitions($entity) as $def) {
            $entry = TextEntry::make(static::VALUE_COLUMN.'.'.$def['key'])
                ->label($def['label'])
                ->placeholder('—');

            if ($def['type'] === 'date') {
                $entry = $entry->date('d M Y');
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    protected static function parseOptions(mixed $options): array
    {
        if (is_array($options)) {
            $list = $options;
        } elseif (is_string($options)) {
            $list = preg_split('/\r\n|\r|\n/', $options) ?: [];
        } else {
            $list = [];
        }

        return array_values(array_filter(array_map(
            static fn ($o) => trim((string) $o),
            $list,
        ), static fn ($o) => $o !== ''));
    }

    protected static function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';

        return trim($slug, '_');
    }
}
