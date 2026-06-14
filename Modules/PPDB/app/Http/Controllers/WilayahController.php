<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint cascade wilayah untuk form biodata portal SPMB (Vue). Read-only,
 * nilai disimpan & dicocokkan berdasarkan NAMA (sama persis dgn SantriResource:
 * provinces → cities(province_id) → districts(city_id) → villages(district_id)).
 * Output = daftar nama (string) urut abjad.
 */
class WilayahController extends Controller
{
    public function provinsi(): JsonResponse
    {
        return response()->json($this->names(
            fn () => DB::table('provinces')->orderBy('name')->pluck('name')->all()
        ));
    }

    public function kabupaten(Request $request): JsonResponse
    {
        $provinsi = trim((string) $request->query('provinsi', ''));

        return response()->json($this->names(function () use ($provinsi) {
            if ($provinsi === '') {
                return [];
            }
            $province = DB::table('provinces')->where('name', $provinsi)->first();
            if (! $province) {
                return [];
            }

            return DB::table('cities')
                ->where('province_id', $province->id)
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }));
    }

    public function kecamatan(Request $request): JsonResponse
    {
        $kabupaten = trim((string) $request->query('kabupaten', ''));

        return response()->json($this->names(function () use ($kabupaten) {
            if ($kabupaten === '') {
                return [];
            }
            $city = DB::table('cities')->where('name', $kabupaten)->first();
            if (! $city) {
                return [];
            }

            return DB::table('districts')
                ->where('city_id', $city->id)
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }));
    }

    public function desa(Request $request): JsonResponse
    {
        $kecamatan = trim((string) $request->query('kecamatan', ''));

        return response()->json($this->names(function () use ($kecamatan) {
            if ($kecamatan === '') {
                return [];
            }
            $district = DB::table('districts')->where('name', $kecamatan)->first();
            if (! $district) {
                return [];
            }

            return DB::table('villages')
                ->where('district_id', $district->id)
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }));
    }

    /**
     * Jalankan query nama wilayah dengan aman (tabel wilayah bisa belum terisi
     * di sebagian tenant → kembalikan [] alih-alih error).
     *
     * @param  \Closure(): array<int, mixed>  $fetch
     * @return array<int, string>
     */
    private function names(\Closure $fetch): array
    {
        try {
            return array_values(array_map('strval', $fetch()));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
