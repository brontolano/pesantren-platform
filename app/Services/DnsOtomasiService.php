<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * DNS custom domain tenant — dua opsi (keputusan owner issue #350 poin 4):
 *
 *  1. MANUAL  : pesantren mengarahkan A record sendiri ke IP VPS platform.
 *               cekDns() memverifikasi domain sudah resolve ke IP yang benar.
 *  2. OTOMASI : buatARecord() membuat A record via API Hostinger DNS —
 *               hanya berlaku bila zona domain dikelola di akun Hostinger owner.
 *               Tanpa token (HOSTINGER_API_TOKEN), otomasi nonaktif.
 *
 * Semua kegagalan ditelan menjadi pesan ramah (tidak melempar exception / 500).
 * Lookup DNS dipisah ke method protected agar bisa di-mock di test.
 */
class DnsOtomasiService
{
    /** Otomasi tersedia? (token Hostinger terisi) */
    public function otomasiAktif(): bool
    {
        return filled(config('dns.hostinger.token'));
    }

    /** IP publik VPS platform — target A record. */
    public function serverIp(): string
    {
        return (string) config('dns.server_ip');
    }

    /** Instruksi manual siap tampil di UI/notifikasi. */
    public function instruksiManual(string $domain): string
    {
        return "Arahkan A record untuk {$domain} ke {$this->serverIp()} di panel DNS domain Anda "
            .'(TTL 3600). Propagasi biasanya 1–24 jam, lalu klik "Cek DNS" untuk memverifikasi.';
    }

    /**
     * OPSI MANUAL — cek apakah domain sudah resolve ke IP VPS.
     *
     * @return array{ok:bool, ips:array<int,string>, pesan:string}
     */
    public function cekDns(string $domain): array
    {
        $ip = $this->serverIp();
        $ips = $this->aRecords($domain);

        if ($ips === []) {
            return [
                'ok' => false,
                'ips' => [],
                'pesan' => "Domain {$domain} belum resolve (A record tidak ditemukan). "
                    .$this->instruksiManual($domain),
            ];
        }

        if (in_array($ip, $ips, true)) {
            return [
                'ok' => true,
                'ips' => $ips,
                'pesan' => "DNS benar: {$domain} sudah mengarah ke {$ip}. SSL akan terbit otomatis saat domain diakses.",
            ];
        }

        return [
            'ok' => false,
            'ips' => $ips,
            'pesan' => "Domain {$domain} mengarah ke ".implode(', ', $ips)
                ." — bukan IP platform ({$ip}). ".$this->instruksiManual($domain),
        ];
    }

    /**
     * OPSI OTOMASI — buat/perbarui A record via API Hostinger DNS.
     * Zona dicari dari domain penuh lalu induknya (pesantren.asy-syifaa.com → asy-syifaa.com).
     *
     * @return array{ok:bool, zona:?string, pesan:string, fallback_manual:bool}
     */
    public function buatARecord(string $domain): array
    {
        $domain = strtolower(trim($domain, ". \t"));

        if (! $this->otomasiAktif()) {
            return [
                'ok' => false,
                'zona' => null,
                'pesan' => 'Otomasi DNS nonaktif: HOSTINGER_API_TOKEN belum diisi di ENV. '
                    .$this->instruksiManual($domain),
                'fallback_manual' => true,
            ];
        }

        try {
            $zona = $this->cariZona($domain);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'zona' => null,
                'pesan' => 'Gagal menghubungi API Hostinger (jaringan/timeout). '
                    .$this->instruksiManual($domain),
                'fallback_manual' => true,
            ];
        }

        if ($zona === null) {
            return [
                'ok' => false,
                'zona' => null,
                'pesan' => "Zona {$domain} tidak ditemukan di akun Hostinger — domain ini dikelola di tempat lain. "
                    .$this->instruksiManual($domain),
                'fallback_manual' => true,
            ];
        }

        $name = $domain === $zona ? '@' : substr($domain, 0, -(strlen($zona) + 1));

        try {
            $res = $this->http()->put("/api/dns/v1/zones/{$zona}", [
                'overwrite' => false,
                'zone' => [[
                    'name' => $name,
                    'type' => 'A',
                    'ttl' => (int) config('dns.hostinger.ttl', 3600),
                    'records' => [['content' => $this->serverIp()]],
                ]],
            ]);
        } catch (\Throwable $e) {
            $res = null;
        }

        if ($res !== null && $res->successful()) {
            return [
                'ok' => true,
                'zona' => $zona,
                'pesan' => "A record {$domain} → {$this->serverIp()} berhasil dibuat otomatis di Hostinger (zona {$zona}). "
                    .'Tunggu propagasi sebentar lalu klik "Cek DNS".',
                'fallback_manual' => false,
            ];
        }

        return [
            'ok' => false,
            'zona' => $zona,
            'pesan' => "Gagal membuat A record otomatis di zona {$zona}"
                .($res !== null ? ' (HTTP '.$res->status().')' : '').'. '
                .$this->instruksiManual($domain),
            'fallback_manual' => true,
        ];
    }

    /** Cari zona Hostinger: coba domain penuh, lalu potong label kiri hingga tersisa 2. */
    protected function cariZona(string $domain): ?string
    {
        $labels = array_values(array_filter(explode('.', $domain)));

        while (count($labels) >= 2) {
            $kandidat = implode('.', $labels);
            $res = $this->http()->get("/api/dns/v1/zones/{$kandidat}");

            if ($res->successful()) {
                return $kandidat;
            }
            // 404/403 = zona bukan milik akun ini → coba induk.
            array_shift($labels);
        }

        return null;
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl((string) config('dns.hostinger.base_url'))
            ->withToken((string) config('dns.hostinger.token'))
            ->acceptJson()
            ->timeout(max(1, (int) config('dns.hostinger.timeout', 8)));
    }

    /** @return array<int,string> IP dari A record domain (mockable di test). */
    protected function aRecords(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_A) ?: [];

        return array_values(array_filter(array_map(
            static fn (array $r) => $r['ip'] ?? null,
            $records,
        )));
    }
}
