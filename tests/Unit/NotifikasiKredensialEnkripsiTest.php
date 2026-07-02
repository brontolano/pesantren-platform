<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Crypt;
use Modules\Notifikasi\Support\NotifikasiSettings;
use Tests\TestCase;

/**
 * Enkripsi kredensial notifikasi (webhook URL / API key WAHA):
 * - round-trip: conceal() lalu reveal() mengembalikan nilai asli;
 * - kompat-mundur: nilai lama PLAINTEXT (belum terenkripsi) tetap terbaca;
 * - kosong ⇒ null (tak menyimpan blob enkripsi tak berguna).
 */
class NotifikasiKredensialEnkripsiTest extends TestCase
{
    public function test_round_trip_conceal_reveal(): void
    {
        $plain = 'http://n8n:5678/webhook/ppdb-wa?token=rahasia123';

        $stored = NotifikasiSettings::conceal($plain);

        $this->assertNotNull($stored);
        $this->assertNotSame($plain, $stored, 'Nilai tersimpan harus terenkripsi, bukan plaintext.');
        $this->assertSame($plain, NotifikasiSettings::reveal($stored));
    }

    public function test_nilai_lama_plaintext_tetap_terbaca(): void
    {
        // Simulasi record lama sebelum fitur enkripsi: value plaintext.
        $legacy = 'http://n8n:5678/webhook/ppdb-wa';

        $this->assertSame($legacy, NotifikasiSettings::reveal($legacy));
    }

    public function test_conceal_kosong_mengembalikan_null(): void
    {
        $this->assertNull(NotifikasiSettings::conceal(null));
        $this->assertNull(NotifikasiSettings::conceal(''));
        $this->assertNull(NotifikasiSettings::conceal('   '));
    }

    public function test_reveal_null_aman(): void
    {
        $this->assertNull(NotifikasiSettings::reveal(null));
    }

    public function test_reveal_menerima_ciphertext_crypt_langsung(): void
    {
        $cipher = Crypt::encryptString('kunci-waha-abc');

        $this->assertSame('kunci-waha-abc', NotifikasiSettings::reveal($cipher));
    }
}
