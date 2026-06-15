<?php

namespace App\Support;

use App\Models\Setting;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Mesin pemetaan kolom import (fleksibilitas Level 2 & 3).
 *
 * Tujuan: tiap pesantren boleh punya nama/urutan kolom Excel berbeda. Mesin ini
 * membaca header file mereka, memetakannya ke kolom KANONIK yang dipahami
 * importer, lalu menulis ulang menjadi CSV ber-header kanonik — sehingga service
 * importer yang sudah ada TIDAK perlu diubah (cukup diberi file ternormalisasi).
 *
 * Level 3: profil pemetaan disimpan per tenant di Setting (key per entitas),
 * jadi pesantren tak perlu memetakan ulang tiap impor.
 *
 * Murni & dapat diuji: deteksi header, auto-match, dan normalisasi tidak butuh
 * tenancy; hanya simpan/baca profil yang menyentuh Setting (tenant-scoped).
 */
class ImportColumnMapper
{
    /**
     * Header (baris pertama non-kosong) dari file, sebagai list string mentah.
     *
     * @return list<string>
     */
    public function headers(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $this->rowValues($row);
                    if ($this->isEmptyRow($values)) {
                        continue;
                    }

                    return array_map(fn ($v): string => trim((string) $v), $values);
                }
            }
        } finally {
            $reader->close();
        }

        return [];
    }

    /**
     * Tebakan pemetaan awal: untuk tiap kolom kanonik, cari header file yang
     * cocok (sama setelah dinormalisasi) — langsung atau lewat alias opsional.
     *
     * @param  list<string>  $fileHeaders
     * @param  list<string>  $columns  kolom kanonik
     * @param  array<string, list<string>>  $aliases  kanonik => alias tambahan
     * @return array<string, string>  kanonik => header file (atau '' bila tak ketemu)
     */
    public function autoMatch(array $fileHeaders, array $columns, array $aliases = []): array
    {
        $norm = [];
        foreach ($fileHeaders as $h) {
            $norm[$this->normalize($h)] = $h;
        }

        $map = [];
        foreach ($columns as $col) {
            $kandidat = array_merge([$col], $aliases[$col] ?? []);
            $map[$col] = '';
            foreach ($kandidat as $k) {
                $n = $this->normalize($k);
                if (isset($norm[$n])) {
                    $map[$col] = $norm[$n];
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Tulis ulang file ke CSV sementara ber-header KANONIK sesuai mapping.
     * mapping: kanonik => header file (string kosong = kolom tak dipetakan → kosong).
     *
     * @param  list<string>  $columns
     * @param  array<string, string>  $mapping
     * @return string  path CSV sementara (hapus setelah dipakai)
     */
    public function normalizeToCsv(string $path, array $columns, array $mapping): string
    {
        $fileHeaders = $this->headers($path);

        // Index tiap header file (ternormalisasi) → posisi kolom.
        $indexByNorm = [];
        foreach ($fileHeaders as $i => $h) {
            $indexByNorm[$this->normalize($h)] = $i;
        }

        // Untuk tiap kolom kanonik, tentukan index sumbernya (atau null).
        $sourceIndex = [];
        foreach ($columns as $col) {
            $actual = trim((string) ($mapping[$col] ?? ''));
            $sourceIndex[$col] = $actual !== '' && isset($indexByNorm[$this->normalize($actual)])
                ? $indexByNorm[$this->normalize($actual)]
                : null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mapimp').'.csv';
        $out = fopen($tmp, 'w');
        fputcsv($out, $columns); // header kanonik

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);
        try {
            $headerDilewati = false;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $this->rowValues($row);
                    if ($this->isEmptyRow($values)) {
                        continue;
                    }
                    if (! $headerDilewati) {
                        $headerDilewati = true; // baris header

                        continue;
                    }
                    $line = [];
                    foreach ($columns as $col) {
                        $idx = $sourceIndex[$col];
                        $val = $idx !== null ? ($values[$idx] ?? '') : '';
                        $line[] = $val instanceof \DateTimeInterface ? $val->format('Y-m-d') : (string) $val;
                    }
                    fputcsv($out, $line);
                }
            }
        } finally {
            $reader->close();
            fclose($out);
        }

        return $tmp;
    }

    /** Simpan profil pemetaan per tenant (Level 3). */
    public function saveProfile(string $entitas, array $mapping): void
    {
        Setting::updateOrCreate(['key' => $this->profileKey($entitas)], ['value' => $mapping]);
    }

    /** @return array<string, string> */
    public function loadProfile(string $entitas): array
    {
        $v = Setting::getTenantValue($this->profileKey($entitas), []);

        return is_array($v) ? $v : [];
    }

    private function profileKey(string $entitas): string
    {
        return 'import.mapping.'.str()->slug($entitas);
    }

    private function normalize(string $h): string
    {
        return str_replace([' ', '-', '.'], '_', mb_strtolower(trim($h)));
    }

    /** @return list<mixed> */
    private function rowValues(Row $row): array
    {
        return array_map(fn ($cell) => $cell?->getValue(), $row->getCells());
    }

    /** @param list<mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
