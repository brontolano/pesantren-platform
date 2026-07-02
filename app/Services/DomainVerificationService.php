<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Verifikasi kepemilikan domain/subdomain tenant (task Integrasi).
 *
 * Alur: issueToken() → pemilik memasang bukti (salah satu):
 *   1. TXT record DNS:  pesantren-verify=<token>
 *   2. Berkas https://<domain>/.well-known/pesantren-verify.txt berisi <token>
 * lalu verify() mengecek kedua jalur dan menyetel verified_at.
 *
 * Lookup DNS/HTTP dipisah ke method protected agar bisa di-mock di test.
 */
class DomainVerificationService
{
    public const TXT_PREFIX = 'pesantren-verify=';

    /** Terbitkan (atau ambil) token verifikasi untuk satu domain. */
    public function issueToken(Domain $domain): string
    {
        if (blank($domain->verification_token)) {
            $domain->forceFill(['verification_token' => Str::random(40)])->save();
        }

        return (string) $domain->verification_token;
    }

    /**
     * Cek bukti kepemilikan. Sukses → set verified_at.
     *
     * @return array{verified:bool, method:?string}
     */
    public function verify(Domain $domain): array
    {
        $token = (string) $domain->verification_token;
        if ($token === '') {
            return ['verified' => false, 'method' => null];
        }

        if ($this->hasTxtProof($domain->domain, $token)) {
            $domain->forceFill(['verified_at' => now()])->save();

            return ['verified' => true, 'method' => 'dns-txt'];
        }

        if ($this->hasWellKnownProof($domain->domain, $token)) {
            $domain->forceFill(['verified_at' => now()])->save();

            return ['verified' => true, 'method' => 'well-known'];
        }

        return ['verified' => false, 'method' => null];
    }

    /** TXT record berisi "pesantren-verify=<token>"? */
    protected function hasTxtProof(string $domain, string $token): bool
    {
        foreach ($this->txtRecords($domain) as $txt) {
            if (trim($txt) === self::TXT_PREFIX.$token) {
                return true;
            }
        }

        return false;
    }

    /** Berkas .well-known berisi token? (fallback utk pemilik tanpa akses DNS) */
    protected function hasWellKnownProof(string $domain, string $token): bool
    {
        $body = $this->fetchWellKnown($domain);

        return $body !== null && trim($body) === $token;
    }

    /** @return array<int,string> Seluruh nilai TXT pada domain (mockable). */
    protected function txtRecords(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_TXT) ?: [];

        return array_values(array_filter(array_map(
            static fn (array $r) => $r['txt'] ?? null,
            $records,
        )));
    }

    /** Isi https://<domain>/.well-known/pesantren-verify.txt (mockable). */
    protected function fetchWellKnown(string $domain): ?string
    {
        try {
            $res = Http::timeout(8)->get("https://{$domain}/.well-known/pesantren-verify.txt");

            return $res->successful() ? $res->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
