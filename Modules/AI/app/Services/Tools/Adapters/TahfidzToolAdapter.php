<?php

namespace Modules\AI\Services\Tools\Adapters;

use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Services\TahfidzService;

/**
 * T-A033 — adapter READ-ONLY ke modul Tahfidz (Blueprint §6):
 * getTahfidz → TahfidzService::getRingkasanHafalan() + getRataRataNilai()
 * (method baca resmi yang tersedia). TIDAK ADA tulisan apa pun di kelas ini.
 */
class TahfidzToolAdapter
{
    public function __construct(
        private readonly TahfidzService $tahfidz,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getTahfidz(array $args, ?User $pemanggil = null): array
    {
        $santri = Santri::query()->findOrFail((int) $args['santri_id']);

        $ringkasan = $this->tahfidz->getRingkasanHafalan($santri);
        $ringkasan['rata_rata_nilai'] = $this->tahfidz->getRataRataNilai($santri);

        return [
            $ringkasan,
            'Modules\Tahfidz — TahfidzService::getRingkasanHafalan()',
        ];
    }
}
