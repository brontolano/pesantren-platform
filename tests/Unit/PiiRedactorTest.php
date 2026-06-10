<?php

namespace Tests\Unit;

use Modules\AI\Services\Privacy\PiiRedactor;
use Tests\TestCase;

/**
 * Unit PiiRedactor (T-A005): tiap pola Indonesia, gabungan, determinisme
 * token per nilai, idempoten, deredact, dan kontrol via config.
 */
class PiiRedactorTest extends TestCase
{
    private function redactor(): PiiRedactor
    {
        return new PiiRedactor;
    }

    public function test_redaksi_nik_16_digit(): void
    {
        $hasil = $this->redactor()->redact('NIK santri: 3204121212880001 tercatat.');

        $this->assertSame('NIK santri: [PII:NIK:1] tercatat.', $hasil->text);
        $this->assertSame(['[PII:NIK:1]' => '3204121212880001'], $hasil->map);
        $this->assertSame(['nik' => 1], $hasil->counts);
    }

    public function test_redaksi_no_hp_berbagai_format(): void
    {
        foreach ([
            '081234567890',
            '+6281234567890',
            '6281234567890',
            '0812-3456-7890',
            '0812 3456 7890',
        ] as $hp) {
            $hasil = $this->redactor()->redact("Hubungi {$hp} segera.");

            $this->assertSame('Hubungi [PII:HP:1] segera.', $hasil->text, "Format gagal: {$hp}");
            $this->assertSame($hp, $hasil->map['[PII:HP:1]']);
        }
    }

    public function test_redaksi_email(): void
    {
        $hasil = $this->redactor()->redact('Kirim ke wali.santri+1@contoh.sch.id ya.');

        $this->assertSame('Kirim ke [PII:EMAIL:1] ya.', $hasil->text);
    }

    public function test_redaksi_nisn_10_digit(): void
    {
        $hasil = $this->redactor()->redact('NISN: 0061231234.');

        $this->assertSame('NISN: [PII:NISN:1].', $hasil->text);
    }

    public function test_redaksi_rekening_hanya_dengan_kata_kunci_konteks(): void
    {
        $hasil = $this->redactor()->redact('Transfer ke rekening 123456789012 a.n. yayasan.');
        $this->assertSame('Transfer ke rekening [PII:REKENING:1] a.n. yayasan.', $hasil->text);

        $hasil = $this->redactor()->redact('No. rek 9876543210123 BSI.');
        $this->assertStringContainsString('[PII:REKENING:1]', $hasil->text);

        // 12 digit TANPA kata kunci: bukan rekening (dan bukan NIK/NISN/HP).
        $polos = $this->redactor()->redact('Kode inventaris 123456789012 gudang.');
        $this->assertSame('Kode inventaris 123456789012 gudang.', $polos->text);
    }

    public function test_alamat_tidak_diredaksi_keputusan_desain(): void
    {
        // Alamat sengaja TIDAK diredaksi (false positive regex terlalu tinggi)
        // — lihat docblock PiiRedactor. Test ini mengunci keputusan tsb.
        $teks = 'Alamat: Jl. Pesantren No. 12, RT 03 RW 05, Desa Cikoneng.';

        $this->assertSame($teks, $this->redactor()->redact($teks)->text);
    }

    public function test_gabungan_semua_jenis_dan_token_deterministik_per_nilai(): void
    {
        $teks = 'NIK 3204121212880001, HP 081234567890, email a@b.co, NISN 0061231234, '
            .'norek 1234567890123. Ulangi HP 081234567890 dan NIK 3204121212880001.';

        $hasil = $this->redactor()->redact($teks);

        // Nilai sama → token sama (deterministik per panggilan).
        $this->assertSame(2, substr_count($hasil->text, '[PII:HP:1]'));
        $this->assertSame(2, substr_count($hasil->text, '[PII:NIK:1]'));
        $this->assertSame(
            ['email' => 1, 'rekening' => 1, 'nik' => 1, 'hp' => 1, 'nisn' => 1],
            $hasil->counts,
        );
        $this->assertSame(5, $hasil->total());

        // Tidak ada PII mentah tersisa.
        foreach (['3204121212880001', '081234567890', 'a@b.co', '0061231234', '1234567890123'] as $pii) {
            $this->assertStringNotContainsString($pii, $hasil->text);
        }
    }

    public function test_nilai_berbeda_jenis_sama_mendapat_nomor_urut_berbeda(): void
    {
        $hasil = $this->redactor()->redact('HP ayah 081234567890, HP ibu 085612345678.');

        $this->assertSame('HP ayah [PII:HP:1], HP ibu [PII:HP:2].', $hasil->text);
        $this->assertSame(['hp' => 2], $hasil->counts);
    }

    public function test_idempoten_meredaksi_teks_terredaksi_tidak_mengubah_apa_pun(): void
    {
        $sekali = $this->redactor()->redact('NIK 3204121212880001, HP 081234567890.');
        $dua = $this->redactor()->redact($sekali->text);

        $this->assertSame($sekali->text, $dua->text);
        $this->assertSame([], $dua->map);
    }

    public function test_deredact_memulihkan_nilai_asli(): void
    {
        $redactor = $this->redactor();
        $hasil = $redactor->redact('Hubungi wali di 081234567890 (email: wali@contoh.id).');

        $jawaban = 'Baik, kami akan menghubungi [PII:HP:1] dan menembusi [PII:EMAIL:1].';

        $this->assertSame(
            'Baik, kami akan menghubungi 081234567890 dan menembusi wali@contoh.id.',
            $redactor->deredact($jawaban, $hasil->map),
        );
    }

    public function test_deredact_peta_kosong_mengembalikan_teks_apa_adanya(): void
    {
        $this->assertSame('halo', $this->redactor()->deredact('halo', []));
    }

    public function test_pola_bisa_dimatikan_per_jenis_via_config(): void
    {
        config()->set('ai.privacy.redaksi.pola.nisn.aktif', false);

        $hasil = $this->redactor()->redact('NISN: 0061231234.');

        $this->assertSame('NISN: 0061231234.', $hasil->text);
    }

    public function test_pola_tambahan_via_config_tanpa_ubah_kelas(): void
    {
        config()->set('ai.privacy.redaksi.pola_tambahan', [
            'plat' => '/\b[A-Z]{1,2}\s\d{1,4}\s[A-Z]{1,3}\b/u',
        ]);

        $hasil = $this->redactor()->redact('Mobil jemputan D 1234 ABC tiba.');

        $this->assertSame('Mobil jemputan [PII:PLAT:1] tiba.', $hasil->text);
    }

    public function test_redaksi_bisa_dimatikan_total_via_config(): void
    {
        config()->set('ai.privacy.redaksi.enabled', false);

        $hasil = $this->redactor()->redact('NIK 3204121212880001.');

        $this->assertSame('NIK 3204121212880001.', $hasil->text);
        $this->assertSame([], $hasil->map);
    }
}
