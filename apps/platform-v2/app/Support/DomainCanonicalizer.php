<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class DomainCanonicalizer
{
    public function normalize(string $domain): string
    {
        $domain = trim(mb_strtolower($domain));
        $domain = rtrim($domain, '.');

        if (str_contains($domain, '://') || str_contains($domain, '/') || str_contains($domain, ':')) {
            throw ValidationException::withMessages(['canonical_domain' => 'Masukkan nama domain saja, tanpa protokol, port, atau path.']);
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                throw ValidationException::withMessages(['canonical_domain' => 'Nama domain tidak valid. Periksa kembali penulisannya.']);
            }
            $domain = strtolower($ascii);
        }

        if (strlen($domain) > 253 || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))+$/', $domain)) {
            throw ValidationException::withMessages(['canonical_domain' => 'Masukkan domain lengkap, misalnya pesantren.example.']);
        }

        return $domain;
    }
}
