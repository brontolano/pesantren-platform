<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Http\JsonResponse;

class WilayahController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json(Province::orderBy('name')->get());
    }

    public function cities(string $provinceId): JsonResponse
    {
        return response()->json(City::where('province_id', $provinceId)->orderBy('name')->get());
    }

    public function districts(string $cityId): JsonResponse
    {
        return response()->json(District::where('city_id', $cityId)->orderBy('name')->get());
    }

    public function villages(string $districtId): JsonResponse
    {
        return response()->json(Village::where('district_id', $districtId)->orderBy('name')->get());
    }
}
