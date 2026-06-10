<?php

namespace Modules\AI\Services\Privacy;

/**
 * Hasil redaksi PII (T-A005).
 *
 * $map = peta token → nilai asli, HANYA hidup in-memory selama satu request.
 * JANGAN dipersist apa adanya — bila suatu saat perlu disimpan (mis. job
 * async yang de-redaksi belakangan), WAJIB dienkripsi (Crypt::encryptString)
 * sebelum menyentuh storage/queue apa pun. Lihat TODO di PiiRedactor.
 */
final readonly class RedactionResult
{
    /**
     * @param  string  $text  Teks dengan PII sudah diganti token [PII:JENIS:n].
     * @param  array<string, string>  $map  Peta token → nilai asli (in-memory).
     * @param  array<string, int>  $counts  Jumlah nilai unik terredaksi per jenis.
     */
    public function __construct(
        public string $text,
        public array $map,
        public array $counts,
    ) {}

    public function total(): int
    {
        return array_sum($this->counts);
    }

    /** @return list<string> */
    public function jenis(): array
    {
        return array_keys($this->counts);
    }
}
