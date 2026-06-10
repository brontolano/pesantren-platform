<?php

namespace Modules\AI\Services\Metering;

/**
 * T-A002 — status anggaran AI satu tenant hasil BudgetGuard::cek().
 */
final readonly class BudgetStatus
{
    public function __construct(
        public bool $boleh,
        public float $persenTerpakai,
        public bool $killSwitch,
    ) {}

    /** Status permisif — dipakai saat pengecekan anggaran sendiri gagal (fail-open). */
    public static function permisif(): self
    {
        return new self(boleh: true, persenTerpakai: 0.0, killSwitch: false);
    }
}
