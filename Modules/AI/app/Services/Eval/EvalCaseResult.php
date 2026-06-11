<?php

namespace Modules\AI\Services\Eval;

/**
 * T-A024 — hasil evaluasi satu kasus: lulus / gagal (dengan alasan teknis) /
 * dilewati (tidak ikut penyebut skor — mis. kasus ber-setup pada tenant nyata).
 */
final readonly class EvalCaseResult
{
    public const STATUS_LULUS = 'lulus';

    public const STATUS_GAGAL = 'gagal';

    public const STATUS_DILEWATI = 'dilewati';

    public function __construct(
        public string $kode,
        public string $status,
        public string $alasan = '',
    ) {}

    public static function lulus(string $kode): self
    {
        return new self($kode, self::STATUS_LULUS);
    }

    public static function gagal(string $kode, string $alasan): self
    {
        return new self($kode, self::STATUS_GAGAL, $alasan);
    }

    public static function dilewati(string $kode, string $alasan): self
    {
        return new self($kode, self::STATUS_DILEWATI, $alasan);
    }
}
