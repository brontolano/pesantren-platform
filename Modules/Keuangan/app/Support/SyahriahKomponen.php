<?php

namespace Modules\Keuangan\Support;

/**
 * Helper rincian komponen syahriah (#332).
 *
 * Satu tagihan syahriah pesantren sering berlapis: makan, listrik, madrasah
 * diniyah, kas kamar, tabungan wajib, bisyaroh. Komponen disimpan sebagai
 * array JSON of {nama:string, nominal:float, masuk_tabungan:bool}.
 */
final class SyahriahKomponen
{
    /**
     * Normalisasi input komponen (dari form/seed): buang baris kosong,
     * paksa tipe, nominal negatif → 0.
     *
     * @return array<int, array{nama:string, nominal:float, masuk_tabungan:bool}>
     */
    public static function normalize(?array $komponen): array
    {
        if (empty($komponen)) {
            return [];
        }

        $bersih = [];
        foreach ($komponen as $item) {
            if (! is_array($item)) {
                continue;
            }
            $nama = trim((string) ($item['nama'] ?? ''));
            $nominal = round(max((float) ($item['nominal'] ?? 0), 0), 2);
            if ($nama === '' || $nominal <= 0) {
                continue;
            }
            $bersih[] = [
                'nama' => $nama,
                'nominal' => $nominal,
                'masuk_tabungan' => (bool) ($item['masuk_tabungan'] ?? false),
            ];
        }

        return $bersih;
    }

    /** Total tagihan = jumlah seluruh komponen. */
    public static function total(?array $komponen): float
    {
        return round(array_sum(array_column(self::normalize($komponen), 'nominal')), 2);
    }

    /** Porsi yang disetor otomatis ke Tabungan santri saat tagihan lunas. */
    public static function porsiTabungan(?array $komponen): float
    {
        $porsi = 0.0;
        foreach (self::normalize($komponen) as $item) {
            if ($item['masuk_tabungan']) {
                $porsi += $item['nominal'];
            }
        }

        return round($porsi, 2);
    }

    /**
     * Template Syahriah siap-pakai — istilah lokal pesantren (syahriah,
     * bisyaroh, infaq). Nominal hanyalah contoh; admin mengedit sebelum simpan.
     *
     * @return array<int, array{nama:string, nominal:float, masuk_tabungan:bool}>
     */
    public static function templateSyahriah(): array
    {
        return [
            ['nama' => 'Syahriah (uang makan)', 'nominal' => 300000.0, 'masuk_tabungan' => false],
            ['nama' => 'Listrik & air', 'nominal' => 25000.0, 'masuk_tabungan' => false],
            ['nama' => 'Madrasah diniyah', 'nominal' => 50000.0, 'masuk_tabungan' => false],
            ['nama' => 'Kas kamar', 'nominal' => 10000.0, 'masuk_tabungan' => false],
            ['nama' => 'Tabungan wajib', 'nominal' => 25000.0, 'masuk_tabungan' => true],
            ['nama' => 'Bisyaroh ustadz', 'nominal' => 25000.0, 'masuk_tabungan' => false],
        ];
    }
}
