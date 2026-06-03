<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use App\Services\IndonesiaRegionImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IndonesiaRegionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_downloads_and_imports_indonesia_regions(): void
    {
        Http::fake([
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/provinces.csv' => Http::response(
                "code,name\n11,ACEH\n12,SUMATERA UTARA\n"
            ),
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/regencies.csv' => Http::response(
                "code,province_code,name\n1101,11,KABUPATEN SIMEULUE\n1271,12,KOTA MEDAN\n"
            ),
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/districts.csv' => Http::response(
                "code,regency_code,name\n1101010,1101,TEUPAH SELATAN\n1271010,1271,MEDAN KOTA\n"
            ),
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/villages.csv' => Http::response(
                "code,district_code,name\n1101010001,1101010,LATIUNG\n1271010001,1271010,KOTA MATSUM I\n"
            ),
        ]);

        $counts = app(IndonesiaRegionImporter::class)->import();

        $this->assertSame([
            'provinces' => 2,
            'cities' => 2,
            'districts' => 2,
            'villages' => 2,
        ], $counts);

        $this->assertDatabaseCount('provinces', 2);
        $this->assertDatabaseCount('cities', 2);
        $this->assertDatabaseCount('districts', 2);
        $this->assertDatabaseCount('villages', 2);

        $this->assertTrue(Province::whereKey('11')->exists());
        $this->assertTrue(City::whereKey('1101')->where('province_id', '11')->exists());
        $this->assertTrue(District::whereKey('1101010')->where('city_id', '1101')->exists());
        $this->assertTrue(Village::whereKey('1101010001')->where('district_id', '1101010')->exists());
    }

    public function test_importer_accepts_alternative_header_names(): void
    {
        Http::fake([
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/provinces.csv' => Http::response(
                "id,province_name\n31,DKI JAKARTA\n"
            ),
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/regencies.csv' => Http::response(
                "id,province_id,regency_name\n3171,31,KOTA JAKARTA SELATAN\n"
            ),
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/districts.csv' => Http::response(
                "id,regency_id,district_name\n3171010,3171,KEBAYORAN BARU\n"
            ),
            'https://raw.githubusercontent.com/ihsaninh/wilayah-indonesia/master/villages.csv' => Http::response(
                "id,district_id,village_name\n3171010001,3171010,SELONG\n"
            ),
        ]);

        app(IndonesiaRegionImporter::class)->import();

        $this->assertTrue(Province::whereKey('31')->where('name', 'DKI JAKARTA')->exists());
        $this->assertTrue(City::whereKey('3171')->where('name', 'KOTA JAKARTA SELATAN')->exists());
        $this->assertTrue(District::whereKey('3171010')->where('name', 'KEBAYORAN BARU')->exists());
        $this->assertTrue(Village::whereKey('3171010001')->where('name', 'SELONG')->exists());
    }
}
