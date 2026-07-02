<?php

namespace Modules\Keuangan\Services;

use Modules\Keuangan\Models\TabunganTopup;

/**
 * Katalog metode pembayaran DEMO gaya checkout marketplace (ala Xendit).
 *
 * SEMUA DATA SIMULASI — tidak ada gateway nyata. Service ini menyediakan:
 *   - all():        katalog lengkap metode (VA / E-Wallet / QRIS / Retail / Kartu).
 *   - find($code):  ambil satu metode by code.
 *   - isValid():    validasi code metode.
 *   - codes():      daftar code valid (untuk Rule::in di FormRequest).
 *   - paymentDetail(TabunganTopup, $code): generator detail pembayaran
 *     deterministik (nomor VA fake per bank, kode retail, payload QRIS,
 *     langkah-langkah) dari topup id + metode.
 *
 * Catatan field "fee": biaya admin DEMO (rupiah). Frontend boleh menampilkan
 * "Gratis" bila fee == 0. Fee TIDAK ikut menambah saldo (saldo = nominal saja).
 */
class PaymentMethodCatalog
{
    /**
     * Katalog mentah. Tiap entri:
     *   code, name, category (VA|EWALLET|QRIS|RETAIL|CARD),
     *   color (hex untuk badge/logo placeholder),
     *   fee (int rupiah, 0 = gratis),
     *   instructionsType (jenis instruksi: va_number|ewallet_redirect|qris|retail_code|card_form).
     *
     * @var array<int, array<string, mixed>>
     */
    private const METHODS = [
        // ── Virtual Account ──────────────────────────────────────────────
        ['code' => 'BCA',       'name' => 'BCA Virtual Account',      'category' => 'VA',      'color' => '#005FAE', 'fee' => 0,    'instructionsType' => 'va_number'],
        ['code' => 'BNI',       'name' => 'BNI Virtual Account',      'category' => 'VA',      'color' => '#EE7203', 'fee' => 0,    'instructionsType' => 'va_number'],
        ['code' => 'BRI',       'name' => 'BRI Virtual Account',      'category' => 'VA',      'color' => '#00529C', 'fee' => 0,    'instructionsType' => 'va_number'],
        ['code' => 'MANDIRI',   'name' => 'Mandiri Virtual Account',  'category' => 'VA',      'color' => '#003D79', 'fee' => 0,    'instructionsType' => 'va_number'],
        ['code' => 'PERMATA',   'name' => 'Permata Virtual Account',  'category' => 'VA',      'color' => '#00857C', 'fee' => 0,    'instructionsType' => 'va_number'],

        // ── E-Wallet ─────────────────────────────────────────────────────
        ['code' => 'OVO',       'name' => 'OVO',        'category' => 'EWALLET', 'color' => '#4C2A86', 'fee' => 0,    'instructionsType' => 'ewallet_redirect'],
        ['code' => 'GOPAY',     'name' => 'GoPay',      'category' => 'EWALLET', 'color' => '#00AED6', 'fee' => 0,    'instructionsType' => 'ewallet_redirect'],
        ['code' => 'DANA',      'name' => 'DANA',       'category' => 'EWALLET', 'color' => '#118EEA', 'fee' => 0,    'instructionsType' => 'ewallet_redirect'],
        ['code' => 'SHOPEEPAY', 'name' => 'ShopeePay',  'category' => 'EWALLET', 'color' => '#EE4D2D', 'fee' => 0,    'instructionsType' => 'ewallet_redirect'],
        ['code' => 'LINKAJA',   'name' => 'LinkAja',    'category' => 'EWALLET', 'color' => '#E61E2A', 'fee' => 0,    'instructionsType' => 'ewallet_redirect'],

        // ── QRIS ─────────────────────────────────────────────────────────
        ['code' => 'QRIS',      'name' => 'QRIS',       'category' => 'QRIS',    'color' => '#D71149', 'fee' => 0,    'instructionsType' => 'qris'],

        // ── Retail / Gerai ───────────────────────────────────────────────
        ['code' => 'ALFAMART',  'name' => 'Alfamart',   'category' => 'RETAIL',  'color' => '#ED1C24', 'fee' => 2500, 'instructionsType' => 'retail_code'],
        ['code' => 'INDOMARET', 'name' => 'Indomaret',  'category' => 'RETAIL',  'color' => '#1B4DA1', 'fee' => 2500, 'instructionsType' => 'retail_code'],

        // ── Kartu Kredit / Debit ─────────────────────────────────────────
        ['code' => 'CARD',      'name' => 'Kartu Kredit / Debit', 'category' => 'CARD', 'color' => '#1A1A2E', 'fee' => 0, 'instructionsType' => 'card_form'],
    ];

    /**
     * Label kategori (Bahasa Indonesia) untuk pengelompokan di frontend.
     */
    private const CATEGORY_LABELS = [
        'VA' => 'Transfer Virtual Account',
        'EWALLET' => 'E-Wallet',
        'QRIS' => 'QRIS',
        'RETAIL' => 'Bayar di Gerai/Retail',
        'CARD' => 'Kartu Kredit / Debit',
    ];

    /**
     * Katalog lengkap (untuk props halaman checkout).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(function (array $m): array {
            $m['categoryLabel'] = self::CATEGORY_LABELS[$m['category']] ?? $m['category'];

            return $m;
        }, self::METHODS);
    }

    /**
     * Katalog dikelompokkan per kategori (urut sesuai CATEGORY_LABELS).
     *
     * @return array<int, array{category:string, label:string, methods:array<int, array<string, mixed>>}>
     */
    public function grouped(): array
    {
        $all = $this->all();
        $groups = [];

        foreach (self::CATEGORY_LABELS as $category => $label) {
            $methods = array_values(array_filter($all, fn (array $m): bool => $m['category'] === $category));

            if ($methods !== []) {
                $groups[] = [
                    'category' => $category,
                    'label' => $label,
                    'methods' => $methods,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return array<int, string>
     */
    public function codes(): array
    {
        return array_column(self::METHODS, 'code');
    }

    public function isValid(?string $code): bool
    {
        return $code !== null && in_array($code, $this->codes(), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $code): ?array
    {
        foreach ($this->all() as $method) {
            if ($method['code'] === $code) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Generator detail pembayaran DEMO deterministik untuk satu topup + metode.
     *
     * Output (semua field selalu hadir; yang tidak relevan = null) :
     *   - method (entri katalog),
     *   - nominal (float), fee (int), total (float = nominal + fee),
     *   - expiresAt (ISO-8601, +24 jam dari created_at topup),
     *   - paymentCode (kode bayar universal: VA number / retail payment code),
     *   - vaNumber (untuk VA), bankName,
     *   - retailCode (untuk RETAIL),
     *   - qrString (payload QRIS mentah utk dirender QR di frontend),
     *   - qrImageUrl (URL generator QR eksternal sebagai fallback render),
     *   - ewalletDeeplink (deeplink simulasi untuk E-Wallet),
     *   - steps (array<string> langkah-langkah pembayaran),
     *   - note (catatan tambahan).
     *
     * @return array<string, mixed>
     */
    public function paymentDetail(TabunganTopup $topup, string $code): array
    {
        $method = $this->find($code);

        if ($method === null) {
            // Fallback aman bila code tak dikenal (mis. data lama). Tetap DEMO.
            $method = [
                'code' => $code,
                'name' => $code,
                'category' => 'VA',
                'color' => '#64748B',
                'fee' => 0,
                'instructionsType' => 'va_number',
                'categoryLabel' => 'Transfer',
            ];
        }

        $nominal = (float) $topup->nominal;
        $fee = (int) $method['fee'];
        $total = $nominal + $fee;

        // Seed deterministik dari id topup + code → angka stabil tiap render.
        $seed = abs(crc32($topup->id.'|'.$method['code']));
        $expiresAt = ($topup->created_at ?? now())->copy()->addHours(24)->toIso8601String();

        $detail = [
            'method' => $method,
            'nominal' => $nominal,
            'fee' => $fee,
            'total' => $total,
            'expiresAt' => $expiresAt,
            'paymentCode' => null,
            'vaNumber' => null,
            'bankName' => null,
            'retailCode' => null,
            'qrString' => null,
            'qrImageUrl' => null,
            'ewalletDeeplink' => null,
            'steps' => [],
            'note' => 'Ini transaksi simulasi (DEMO). Tidak ada uang nyata yang ditransfer.',
        ];

        switch ($method['instructionsType']) {
            case 'va_number':
                $prefix = $this->vaPrefix($method['code']);
                // 8 digit dari seed, dipadkan; gabung prefix bank.
                $body = str_pad((string) ($seed % 100000000), 8, '0', STR_PAD_LEFT);
                $va = $prefix.$body;

                $detail['vaNumber'] = $va;
                $detail['paymentCode'] = $va;
                $detail['bankName'] = $method['name'];
                $detail['steps'] = [
                    "Buka aplikasi m-banking / ATM {$method['code']}.",
                    'Pilih menu Transfer ke Virtual Account.',
                    "Masukkan nomor VA: {$va}.",
                    'Pastikan nama tujuan: PESANTREN DEMO dan nominal sesuai.',
                    'Konfirmasi dan selesaikan pembayaran.',
                ];
                break;

            case 'ewallet_redirect':
                $ref = strtoupper($method['code']).'-'.str_pad((string) ($seed % 1000000), 6, '0', STR_PAD_LEFT);
                $detail['paymentCode'] = $ref;
                $detail['ewalletDeeplink'] = strtolower($method['code']).'://pay/demo/'.$ref;
                $detail['steps'] = [
                    "Anda akan diarahkan ke aplikasi {$method['name']} (simulasi).",
                    'Periksa nominal pembayaran: lihat ringkasan di atas.',
                    "Setujui pembayaran di aplikasi {$method['name']}.",
                    'Kembali ke halaman ini, lalu tekan "Saya Sudah Bayar".',
                ];
                break;

            case 'qris':
                $qrPayload = $this->qrisPayload($topup, $total, $seed);
                $detail['qrString'] = $qrPayload;
                $detail['paymentCode'] = 'QRIS-'.str_pad((string) ($seed % 1000000), 6, '0', STR_PAD_LEFT);
                // Fallback render: tidak ada lib QR di server, frontend boleh
                // pakai qrImageUrl ini ATAU render qrString sendiri (mis. qrcode.vue).
                $detail['qrImageUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data='.rawurlencode($qrPayload);
                $detail['steps'] = [
                    'Buka aplikasi pembayaran apa pun yang mendukung QRIS.',
                    'Pindai (scan) kode QR di atas.',
                    'Periksa nominal lalu konfirmasi pembayaran.',
                    'Kembali ke halaman ini, lalu tekan "Saya Sudah Bayar".',
                ];
                break;

            case 'retail_code':
                $retail = strtoupper(substr($method['code'], 0, 3)).str_pad((string) ($seed % 1000000000), 9, '0', STR_PAD_LEFT);
                $detail['retailCode'] = $retail;
                $detail['paymentCode'] = $retail;
                $detail['steps'] = [
                    "Kunjungi gerai {$method['name']} terdekat.",
                    "Sampaikan ke kasir: bayar {$method['name']} / Xendit.",
                    "Tunjukkan kode pembayaran: {$retail}.",
                    'Bayar sesuai nominal (sudah termasuk biaya admin).',
                    'Simpan struk sebagai bukti, lalu tekan "Saya Sudah Bayar".',
                ];
                break;

            case 'card_form':
            default:
                $detail['paymentCode'] = 'CARD-'.str_pad((string) ($seed % 1000000), 6, '0', STR_PAD_LEFT);
                $detail['steps'] = [
                    'Masukkan nomor kartu, masa berlaku, dan CVV (simulasi).',
                    'Sistem akan memverifikasi melalui 3D Secure (simulasi).',
                    'Setelah berhasil, tekan "Saya Sudah Bayar".',
                ];
                break;
        }

        return $detail;
    }

    /**
     * Prefix VA fake per bank (meniru kode biller umum). DEMO.
     */
    private function vaPrefix(string $bank): string
    {
        return match ($bank) {
            'BCA' => '12345',
            'BNI' => '98801',
            'BRI' => '88810',
            'MANDIRI' => '89010',
            'PERMATA' => '85110',
            default => '00000',
        };
    }

    /**
     * Payload QRIS DEMO. BUKAN payload EMVCo valid — hanya string deterministik
     * yang cukup untuk dirender sebagai gambar QR di frontend.
     */
    private function qrisPayload(TabunganTopup $topup, float $total, int $seed): string
    {
        $amount = number_format($total, 2, '.', '');
        $merchant = 'PESANTRENDEMO';
        $ref = str_pad((string) ($seed % 1000000000000), 12, '0', STR_PAD_LEFT);

        // Format pseudo-QRIS: tag-value sederhana (DEMO).
        return implode('', [
            '00020101',
            '021226',
            $merchant,
            '52040000',
            '5303360',
            '54'.str_pad((string) strlen($amount), 2, '0', STR_PAD_LEFT).$amount,
            '5802ID',
            '6304'.substr($ref, 0, 4),
        ]).'|TOPUP#'.$topup->id.'|REF'.$ref;
    }
}
