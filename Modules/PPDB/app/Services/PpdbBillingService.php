<?php

namespace Modules\PPDB\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\TagihanService;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\TagihanPpdb;
use RuntimeException;

class PpdbBillingService
{
    private const ELIGIBLE_STATUSES = [
        'lulus',
        'cadangan',
        'registrasi_ulang',
    ];

    public function __construct(
        private readonly TagihanService $tagihanService,
    ) {
    }

    public function createOrSyncTagihan(CalonSantri|int $calonSantri, array $payload): TagihanPpdb
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($calonSantri, $payload): TagihanPpdb {
            $candidate = $this->resolveCandidate($calonSantri);

            $this->ensureCandidateReadyForBilling($candidate);

            $type = $this->normalizeType($payload['tipe'] ?? null);
            $nominal = $this->normalizeAmount($payload['nominal'] ?? null);
            $diskon = $this->normalizeDiscount($payload['diskon'] ?? 0, $nominal);
            $status = $this->normalizeStatus($payload['status'] ?? null);
            $jatuhTempo = $this->normalizeDueDate($payload['jatuh_tempo'] ?? null);
            $description = $this->resolveDescription($candidate, $type, $payload['keterangan'] ?? null);

            $existing = TagihanPpdb::query()
                ->where('calon_santri_id', $candidate->id)
                ->where('tipe', $type)
                ->lockForUpdate()
                ->first();

            $linkedTagihan = null;
            if ($existing?->keuangan_tagihan_id !== null) {
                $linkedTagihan = Tagihan::query()->find($existing->keuangan_tagihan_id);
            }

            $keuanganTagihan = $this->tagihanService->sinkronkanTagihanUntukSantri(
                $candidate->santri_id,
                [
                    'jenis_tagihan' => $type,
                    'keterangan' => $description,
                    'nominal' => $nominal,
                    'diskon' => $diskon,
                    'jatuh_tempo' => $jatuhTempo,
                    'status' => $status,
                    'periode_akademik' => $payload['periode_akademik'] ?? null,
                    'bulan_masehi' => $payload['bulan_masehi'] ?? null,
                    'bulan_hijriah' => $payload['bulan_hijriah'] ?? null,
                    'created_by' => $payload['created_by'] ?? null,
                ],
                $linkedTagihan
            );

            $ppdbTagihan = TagihanPpdb::query()->updateOrCreate(
                [
                    'tenant_id' => tenancy()->tenant->getTenantKey(),
                    'calon_santri_id' => $candidate->id,
                    'tipe' => $type,
                ],
                [
                    'keuangan_tagihan_id' => $keuanganTagihan->id,
                    'nominal' => $keuanganTagihan->nominal_akhir,
                    'jatuh_tempo' => $keuanganTagihan->jatuh_tempo,
                    'status' => $keuanganTagihan->status,
                    'keterangan' => $description,
                ]
            );

            return $ppdbTagihan->fresh(['calonSantri', 'keuanganTagihan']);
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk tagihan PPDB.');
        }
    }

    private function resolveCandidate(CalonSantri|int $calonSantri): CalonSantri
    {
        if ($calonSantri instanceof CalonSantri) {
            return CalonSantri::query()
                ->whereKey($calonSantri->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        return CalonSantri::query()
            ->whereKey($calonSantri)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureCandidateReadyForBilling(CalonSantri $candidate): void
    {
        if (! in_array($candidate->status_pendaftaran, self::ELIGIBLE_STATUSES, true)) {
            throw new InvalidArgumentException('Tagihan PPDB hanya boleh dibuat untuk calon yang sudah lulus, cadangan, atau registrasi ulang.');
        }

        if ($candidate->santri_id === null) {
            throw new InvalidArgumentException('Tagihan PPDB terhubung ke Keuangan membutuhkan calon yang sudah dikonversi menjadi santri.');
        }
    }

    private function normalizeType(mixed $type): string
    {
        $normalized = strtolower(trim((string) $type));

        if ($normalized === '') {
            throw new InvalidArgumentException('Tipe tagihan PPDB wajib diisi.');
        }

        return str_replace(' ', '_', $normalized);
    }

    private function normalizeAmount(mixed $amount): float
    {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('Nominal tagihan PPDB harus lebih besar dari 0.');
        }

        return (float) $amount;
    }

    private function normalizeDiscount(mixed $discount, float $nominal): float
    {
        if (! is_numeric($discount) || (float) $discount < 0) {
            throw new InvalidArgumentException('Diskon tagihan PPDB tidak valid.');
        }

        $normalized = (float) $discount;

        if ($normalized > $nominal) {
            throw new InvalidArgumentException('Diskon tagihan PPDB tidak boleh lebih besar dari nominal.');
        }

        return $normalized;
    }

    private function normalizeStatus(mixed $status): string
    {
        $normalized = strtolower(trim((string) ($status ?? 'belum_bayar')));
        $allowed = ['belum_bayar', 'sebagian', 'lunas', 'batal'];

        if (! in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException('Status tagihan PPDB harus salah satu dari: belum_bayar, sebagian, lunas, batal.');
        }

        return $normalized;
    }

    private function normalizeDueDate(mixed $date): string
    {
        $normalized = trim((string) $date);

        if ($normalized === '') {
            throw new InvalidArgumentException('Jatuh tempo tagihan PPDB wajib diisi.');
        }

        return now()->parse($normalized)->toDateString();
    }

    private function resolveDescription(CalonSantri $candidate, string $type, mixed $description): string
    {
        $normalized = trim((string) ($description ?? ''));

        if ($normalized !== '') {
            return $normalized;
        }

        return sprintf(
            'Tagihan PPDB %s untuk %s (%s)',
            str_replace('_', ' ', $type),
            $candidate->nama,
            $candidate->nomor_pendaftaran
        );
    }
}
