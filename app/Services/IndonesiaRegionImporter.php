<?php

namespace App\Services;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use SplFileObject;

class IndonesiaRegionImporter
{
    /**
     * @var array<string, string>
     */
    private const DEFAULT_SOURCES = [
        'provinces' => 'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/provinces.csv',
        'cities' => 'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/regencies.csv',
        'districts' => 'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/districts.csv',
        'villages' => 'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/villages.csv',
    ];

    /**
     * @return array<string, int>
     */
    public function import(bool $truncate = true): array
    {
        return DB::transaction(function () use ($truncate): array {
            if ($truncate) {
                $this->truncateTables();
            }

            return [
                'provinces' => $this->importLevel('provinces', Province::class, ['id']),
                'cities' => $this->importLevel('cities', City::class, ['id']),
                'districts' => $this->importLevel('districts', District::class, ['id']),
                'villages' => $this->importLevel('villages', Village::class, ['id']),
            ];
        });
    }

    private function truncateTables(): void
    {
        DB::statement('TRUNCATE TABLE villages, districts, cities, provinces RESTART IDENTITY CASCADE');
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  list<string>  $uniqueBy
     */
    private function importLevel(string $level, string $modelClass, array $uniqueBy): int
    {
        $total = 0;
        $payload = [];

        foreach ($this->rowsFor($level) as $row) {
            $payload[] = $row;
            $total++;

            if (count($payload) >= 1000) {
                $modelClass::query()->upsert($payload, $uniqueBy);
                $payload = [];
            }
        }

        if ($payload !== []) {
            $modelClass::query()->upsert($payload, $uniqueBy);
        }

        return $total;
    }

    /**
     * @return LazyCollection<int, array<string, string>>
     */
    public function rowsFor(string $level): LazyCollection
    {
        return LazyCollection::make(function () use ($level) {
            $path = $this->materializeSource($level);
            $file = new SplFileObject($path, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

            $headers = null;

            foreach ($file as $row) {
                if (! is_array($row) || $row === [null]) {
                    continue;
                }

                if ($headers === null) {
                    $headers = $this->normalizeHeaders($row);
                    continue;
                }

                $assoc = array_combine($headers, array_map(
                    static fn ($value): string => trim((string) $value),
                    array_pad($row, count($headers), '')
                ));

                if ($assoc === false) {
                    continue;
                }

                if (count(array_filter($assoc, static fn (string $value): bool => $value !== '')) === 0) {
                    continue;
                }

                yield $this->normalizeRow($level, $assoc);
            }
        });
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header): string {
            $normalized = strtolower(trim((string) $header));
            $normalized = preg_replace('/^\x{FEFF}/u', '', $normalized) ?? $normalized;

            return str_replace([' ', '-'], '_', $normalized);
        }, $headers);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeRow(string $level, array $row): array
    {
        return match ($level) {
            'provinces' => [
                'id' => $this->pick($row, ['id', 'code', 'province_id', 'province_code']),
                'name' => $this->pick($row, ['name', 'province', 'province_name']),
            ],
            'cities' => [
                'id' => $this->pick($row, ['id', 'code', 'regency_id', 'regency_code', 'city_id', 'city_code']),
                'province_id' => $this->pick($row, ['province_id', 'province_code']),
                'name' => $this->pick($row, ['name', 'regency', 'regency_name', 'city', 'city_name']),
            ],
            'districts' => [
                'id' => $this->pick($row, ['id', 'code', 'district_id', 'district_code']),
                'city_id' => $this->pick($row, ['city_id', 'city_code', 'regency_id', 'regency_code']),
                'name' => $this->pick($row, ['name', 'district', 'district_name']),
            ],
            'villages' => [
                'id' => $this->pick($row, ['id', 'code', 'village_id', 'village_code']),
                'district_id' => $this->pick($row, ['district_id', 'district_code']),
                'name' => $this->pick($row, ['name', 'village', 'village_name', 'subdistrict']),
            ],
            default => throw new RuntimeException("Level wilayah tidak dikenal: {$level}"),
        };
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        throw new RuntimeException('Kolom wajib tidak ditemukan pada dataset wilayah.');
    }

    private function materializeSource(string $level): string
    {
        $source = $this->resolveSource($level);

        if (is_file($source)) {
            return $source;
        }

        $target = storage_path("app/wilayah/{$level}.csv");
        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        Http::timeout(120)
            ->retry(3, 500)
            ->sink($target)
            ->get($source)
            ->throw();

        return $target;
    }

    private function resolveSource(string $level): string
    {
        $env = env('WILAYAH_SOURCE_' . strtoupper($level));

        return is_string($env) && $env !== ''
            ? $env
            : self::DEFAULT_SOURCES[$level];
    }
}
