<?php

namespace Modules\AI\Services\Wa;

/**
 * T-A051 — router intent chatbot WA: RULE-BASED MURNI, TANPA LLM
 * (Blueprint §7.1 template-first: mayoritas trafik wali = keyword intent →
 * tool resmi → template; LLM hanya untuk pertanyaan bebas ber-RAG).
 *
 * Pola hidup di config ai.wa.intents (Aturan Emas #11: kebijakan di
 * konfigurasi, bukan hard-code) — peta TERURUT intent → daftar regex;
 * intent pertama yang polanya cocok menang.
 *
 * GUARD ANTI-KARANG ANGKA (AC T-A051): teks yang menyinggung uang/biaya
 * tapi tidak tertangkap intent mana pun (kalimat panjang) DIPAKSA ke intent
 * 'tagihan' — jawaban nominal HANYA boleh datang dari ToolResult, tidak
 * pernah dari LLM/RAG.
 */
class IntentRouter
{
    /**
     * Tentukan intent sebuah pesan. null = tidak ter-intent (free-form RAG).
     */
    public function route(string $teks): ?string
    {
        $teks = trim($teks);

        if ($teks === '') {
            return null;
        }

        foreach ((array) config('ai.wa.intents', []) as $intent => $daftarPola) {
            if ($this->cocok((array) $daftarPola, $teks)) {
                return (string) $intent;
            }
        }

        // Guard uang: prioritaskan tool SEBELUM free-form RAG.
        if ($this->cocok((array) config('ai.wa.pola_uang', []), $teks)) {
            return 'tagihan';
        }

        return null;
    }

    /** @param  list<string>  $daftarPola */
    private function cocok(array $daftarPola, string $teks): bool
    {
        foreach ($daftarPola as $pola) {
            // Regex config rusak (false) diabaikan — router tidak boleh
            // meledak karena satu pola salah ketik; intent lain tetap dicek.
            if (@preg_match((string) $pola, $teks) === 1) {
                return true;
            }
        }

        return false;
    }
}
