<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use Modules\PPDB\Support\PpdbPhone;
use PHPUnit\Framework\TestCase;

/** Standardisasi nomor telepon Indonesia → 62… (kanonik, idempoten, anti country-code ganda). */
class PhoneNumberTest extends TestCase
{
    /** @return array<int, array{0:string|null, 1:string}> */
    private function cases(): array
    {
        return [
            ['081234567890', '6281234567890'],          // lokal 08
            ['+6281234567890', '6281234567890'],         // plus 62
            ['6281234567890', '6281234567890'],          // sudah 62
            ['+62081234567890', '6281234567890'],        // BUG "+62 62 0" → bersih
            ['620812345678', '62812345678'],             // 62 + 0
            ['6262812345678', '62812345678'],            // 62 ganda
            ['62081234567890', '6281234567890'],         // 62 + 0 panjang
            ['8123456789', '628123456789'],              // bare 8 (tanpa 0/62)
            ['0812 3456-7890', '6281234567890'],         // spasi & strip
            ['0062812345678', '62812345678'],            // 0062 internasional
            ['082111707471 / 0895617831717', '6282111707471'], // 2 nomor "/" → ambil pertama
            ['0812-3456 atau 0857-9999', '628123456'],          // 2 nomor "atau" → ambil pertama
            ['62821117074710895617831717', ''],                 // 2 nomor menyatu tanpa pemisah → tolak
            ['000000000000', ''],                               // placeholder junk → kosong
            ['Sama dengan ayah/ibu kandung', ''],               // teks → kosong
            ['', ''],
            [null, ''],
            ['abc', ''],
        ];
    }

    public function test_normalisasi_kanonik_dan_idempoten(): void
    {
        foreach ($this->cases() as [$input, $expected]) {
            $n = PhoneNumber::normalize($input);
            $this->assertSame($expected, $n, 'Input: '.var_export($input, true));
            // Idempoten: anti country-code ganda saat dipanggil berulang.
            $this->assertSame($n, PhoneNumber::normalize($n), 'Idempoten gagal utk: '.var_export($input, true));
        }
    }

    public function test_ppdbphone_delegasi_konsisten(): void
    {
        foreach ($this->cases() as [$input, $expected]) {
            $this->assertSame($expected, PpdbPhone::normalize((string) $input));
        }
    }

    public function test_helper_tambahan(): void
    {
        $this->assertTrue(PhoneNumber::isValid('081234567890'));
        $this->assertFalse(PhoneNumber::isValid('123'));
        $this->assertFalse(PhoneNumber::isValid(''));
        $this->assertSame('081234567890', PhoneNumber::local('+6281234567890'));
        $this->assertSame('-', PhoneNumber::local(''));
    }
}
