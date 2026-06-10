<?php

namespace Modules\AI\Services\Guardrails;

use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiPolicyRule;

/**
 * T-A022 — Disclaimer injector (Blueprint §4.2 langkah 7, PRD §7.5).
 *
 * Menyisipkan footer wajib sesuai kategori jawaban:
 *  - 'agama'    → daftar sumber rujukan + teks disclaimer PRD §7.5 VERBATIM
 *                 (default config; tenant dapat override via rule T-A020
 *                 tipe 'disclaimer' — teks override pun datang dari data
 *                 seed/ustadz, BUKAN dikarang kode, Aturan Emas #8);
 *  - 'keuangan' / 'akademik' → disclaimer ringkas netral dari config;
 *  - jawaban data/tool → footer "Sumber: {sumber} • per {cap_waktu}";
 *  - 'umum' tanpa konfigurasi → teks dikembalikan apa adanya.
 *
 * Format POLOS kompatibel WA: tanpa heading markdown, tanpa bold/italic —
 * hanya baris teks dan pemisah baris kosong (konsisten lintas kanal).
 */
class DisclaimerInjector
{
    /**
     * @param  list<array{judul: string, bab: string|null, halaman: int|null, label: string}>  $sitasi
     */
    public function inject(
        string $text,
        string $kategori,
        array $sitasi = [],
        ?string $capWaktu = null,
        ?string $sumber = null,
    ): string {
        $bagian = [rtrim($text)];

        // Daftar sumber WAJIB menyertai jawaban keagamaan (PRD §7.5).
        if ($kategori === CitationEnforcer::KATEGORI_AGAMA && $sitasi !== []) {
            $baris = array_map(
                static fn (array $s): string => '- '.(string) ($s['label'] ?? ($s['judul'] ?? '')),
                $sitasi,
            );
            $bagian[] = "Sumber rujukan:\n".implode("\n", $baris);
        }

        // Footer jawaban data: sumber sistem + cap waktu.
        if ($sumber !== null && $capWaktu !== null) {
            $bagian[] = strtr(
                (string) config('ai.guardrails.disclaimer.footer_data', 'Sumber: :sumber • per :cap_waktu'),
                [':sumber' => $sumber, ':cap_waktu' => $capWaktu],
            );
        }

        $disclaimer = $this->teksDisclaimer($kategori);

        if ($disclaimer !== '') {
            $bagian[] = $disclaimer;
        }

        return implode("\n\n", $bagian);
    }

    /**
     * Teks disclaimer kategori: override tenant (rule aktif tipe 'disclaimer'
     * ber-param.kategori cocok, respons_template terisi) > default config.
     * Lookup best-effort — kegagalan baca rule TIDAK menggagalkan jawaban,
     * jatuh ke default config (teks PRD §7.5 untuk agama).
     */
    private function teksDisclaimer(string $kategori): string
    {
        $default = (string) config("ai.guardrails.disclaimer.{$kategori}", '');

        try {
            if (! function_exists('tenancy') || ! tenancy()->initialized) {
                return $default;
            }

            $rule = AiPolicyRule::query()
                ->where('tipe', AiPolicyRule::TIPE_DISCLAIMER)
                ->where('aktif', true)
                ->whereNotNull('respons_template')
                ->whereHas('policy', static fn ($q) => $q->where('aktif', true))
                ->orderBy('prioritas')
                ->orderBy('id')
                ->get()
                ->first(static function (AiPolicyRule $r) use ($kategori): bool {
                    $param = (array) (((array) $r->pola)['param'] ?? []);

                    return (string) ($param['kategori'] ?? '') === $kategori;
                });

            $override = $rule?->respons_template;

            return is_string($override) && trim($override) !== '' ? trim($override) : $default;
        } catch (\Throwable $e) {
            Log::warning('DisclaimerInjector: gagal membaca rule disclaimer tenant — memakai default config.', [
                'kategori' => $kategori,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $default;
        }
    }
}
