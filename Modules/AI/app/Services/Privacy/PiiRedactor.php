<?php

namespace Modules\AI\Services\Privacy;

/**
 * T-A005 — Redaktor PII pra-provider (Blueprint §4.2 langkah 2).
 *
 * Mengganti PII berpola Indonesia (NIK, no HP, email, NISN, no rekening)
 * dengan token deterministik per panggilan: [PII:HP:1], [PII:NIK:1], dst.
 * Nilai asli yang sama dalam satu panggilan selalu mendapat token yang sama.
 *
 * Tetap berlaku walau provider v1 adalah LLM lokal (data tak keluar server):
 * tujuannya menjaga log, audit, dan konteks tetap minimal-PII SEKARANG,
 * supaya saat provider eksternal diaktifkan (keputusan K4) tidak ada jalur
 * yang lupa dipagari.
 *
 * KEPUTUSAN DESAIN — alamat TIDAK diredaksi: pola alamat bebas-format
 * (Jl./Kp./RT-RW/desa) menghasilkan false positive sangat tinggi pada teks
 * pesantren (nama jalan dipakai sebagai nama gedung/halaqah, "Jl." muncul di
 * kutipan dokumen). Redaksi alamat menunggu pendekatan berbasis NER, bukan
 * regex. Pola per jenis bisa dimatikan / ditambah via config ai.privacy.
 *
 * TODO(T-A005-persist): peta de-redaksi saat ini in-memory per request dan
 * TIDAK dipersist. Bila kelak jawaban diproses job async (peta harus
 * menyeberang proses), WAJIB disimpan TERENKRIPSI (Crypt::encryptString)
 * dengan TTL pendek — jangan pernah menulis peta mentah ke DB/queue/log.
 */
class PiiRedactor
{
    /**
     * Redaksi satu teks. Idempoten: meredaksi teks yang sudah terredaksi
     * tidak mengubah apa-apa (token pengganti tidak memuat pola PII).
     */
    public function redact(string $text): RedactionResult
    {
        if ($text === '' || ! (bool) config('ai.privacy.redaksi.enabled', true)) {
            return new RedactionResult($text, [], []);
        }

        $map = [];
        $counts = [];
        $tokenPerNilai = [];

        foreach ($this->patterns() as $jenis => $regex) {
            $text = (string) preg_replace_callback(
                $regex,
                function (array $m) use ($jenis, &$map, &$counts, &$tokenPerNilai): string {
                    $asli = $m[0];

                    // Deterministik per panggilan: nilai sama → token sama.
                    if (isset($tokenPerNilai[$jenis][$asli])) {
                        return $tokenPerNilai[$jenis][$asli];
                    }

                    $counts[$jenis] = ($counts[$jenis] ?? 0) + 1;
                    $token = sprintf('[PII:%s:%d]', strtoupper($jenis), $counts[$jenis]);

                    $map[$token] = $asli;
                    $tokenPerNilai[$jenis][$asli] = $token;

                    return $token;
                },
                $text,
            );
        }

        return new RedactionResult($text, $map, $counts);
    }

    /**
     * Pulihkan token [PII:...] pada jawaban model menjadi nilai asli.
     *
     * @param  array<string, string>  $peta  token → nilai asli (dari RedactionResult::$map).
     */
    public function deredact(string $text, array $peta): string
    {
        return $peta === [] ? $text : strtr($text, $peta);
    }

    /**
     * Pola aktif terurut (urutan PENTING: pola berkata-kunci/lebih spesifik
     * lebih dulu supaya deretan digit tidak salah klaim oleh pola generik).
     *
     * @return array<string, string> jenis => regex
     */
    private function patterns(): array
    {
        $patterns = [];

        $config = (array) config('ai.privacy.redaksi.pola', []);

        foreach ($config as $jenis => $def) {
            if (is_array($def) && (bool) ($def['aktif'] ?? true) && isset($def['regex'])) {
                $patterns[(string) $jenis] = (string) $def['regex'];
            }
        }

        // Pola tambahan operator (config, bukan hardcode di kelas ini).
        foreach ((array) config('ai.privacy.redaksi.pola_tambahan', []) as $jenis => $regex) {
            if (is_string($regex) && $regex !== '') {
                $patterns[(string) $jenis] = $regex;
            }
        }

        return $patterns;
    }
}
