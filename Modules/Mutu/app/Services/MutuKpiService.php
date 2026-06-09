<?php

namespace Modules\Mutu\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Mutu\Models\MutuSasaran;
use Modules\Mutu\Models\MutuSkm;
use Modules\Mutu\Models\MutuTemuan;
use RuntimeException;

/**
 * G3-17 — SPMI/Quality (KMA 939): KPI/sasaran, temuan (Non-Conformance) dengan
 * state-machine tindakan korektif + gerbang persetujuan F1, dan SKM.
 *
 * State-machine temuan: terbuka → tindakan → verifikasi → tutup.
 * Transisi ilegal ditolak di service-layer (konsisten dgn MutuService dokumen).
 */
class MutuKpiService
{
    /** Catat/update realisasi sebuah sasaran KPI. */
    public function catatRealisasi(MutuSasaran|int $sasaran, float $realisasi): MutuSasaran
    {
        $this->ensureTenantContext();
        $s = $this->resolveSasaran($sasaran);
        if ($realisasi < 0) {
            throw new InvalidArgumentException('Realisasi tidak boleh negatif.');
        }
        $s->update(['realisasi' => round($realisasi, 2)]);

        return $s->fresh();
    }

    /** Buat temuan Non-Conformance (status awal terbuka). */
    public function buatTemuan(array $data): MutuTemuan
    {
        $this->ensureTenantContext();
        if (empty($data['judul'])) {
            throw new InvalidArgumentException('Judul temuan wajib diisi.');
        }
        $severity = $data['severity'] ?? 'minor';
        if (! array_key_exists($severity, MutuTemuan::SEVERITY)) {
            throw new InvalidArgumentException('Severity tidak valid.');
        }

        return MutuTemuan::create([
            'inspeksi_id' => $data['inspeksi_id'] ?? null,
            'sasaran_id' => $data['sasaran_id'] ?? null,
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'severity' => $severity,
            'severity_level' => MutuTemuan::SEVERITY_LEVEL[$severity],
            'status' => 'terbuka',
            'tenggat' => $data['tenggat'] ?? null,
            'pic_id' => $data['pic_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /** terbuka → tindakan: catat rencana/tindakan korektif + PIC. */
    public function mulaiTindakan(MutuTemuan|int $temuan, string $tindakan, ?int $picId = null): MutuTemuan
    {
        $this->ensureTenantContext();
        $t = $this->resolveTemuan($temuan);
        $this->pastikanTransisi($t, 'terbuka', 'tindakan');
        if (trim($tindakan) === '') {
            throw new InvalidArgumentException('Tindakan korektif wajib diisi.');
        }

        $t->update([
            'status' => 'tindakan',
            'tindakan_korektif' => $tindakan,
            'pic_id' => $picId ?? $t->pic_id,
        ]);

        return $t->fresh();
    }

    /**
     * tindakan → verifikasi: tindakan selesai, menunggu verifikasi.
     * Bila ada ApprovalRule wajib terpicu (mis. severity tinggi), MEMBUAT
     * ApprovalRequest 'menunggu' (F1) — sehingga penutupan kelak dapat disetujui
     * di inbox. No-op tanpa aturan (pola CutiService/PurchaseOrderService).
     */
    public function ajukanVerifikasi(MutuTemuan|int $temuan, ?int $userId = null): MutuTemuan
    {
        $this->ensureTenantContext();
        $t = $this->resolveTemuan($temuan);
        $this->pastikanTransisi($t, 'tindakan', 'verifikasi');
        $t->update(['status' => 'verifikasi']);
        $t->ajukanPersetujuan($userId); // buat permintaan persetujuan bila wajib

        return $t->fresh();
    }

    /** verifikasi → tutup: gerbang F1 (no-op tanpa aturan) lalu tutup temuan. */
    public function tutup(MutuTemuan|int $temuan, int $userId): MutuTemuan
    {
        $this->ensureTenantContext();
        $t = $this->resolveTemuan($temuan);
        $this->pastikanTransisi($t, 'verifikasi', 'tutup');

        // Gerbang persetujuan F1 untuk penutupan temuan (mis. wajib utk severity tinggi).
        $t->pastikanBolehLanjut();

        $t->update([
            'status' => 'tutup',
            'diverifikasi_oleh' => $userId,
            'diverifikasi_pada' => now(),
        ]);

        return $t->fresh();
    }

    /** Catat satu respons SKM (skor 1..5). */
    public function catatSkm(array $data): MutuSkm
    {
        $this->ensureTenantContext();
        $skor = (int) ($data['skor'] ?? 0);
        if ($skor < 1 || $skor > 5) {
            throw new InvalidArgumentException('Skor SKM harus 1..5.');
        }

        return MutuSkm::create([
            'periode' => $data['periode'] ?? null,
            'aspek' => $data['aspek'] ?? 'umum',
            'responden' => $data['responden'] ?? null,
            'skor' => $skor,
            'komentar' => $data['komentar'] ?? null,
        ]);
    }

    /**
     * Ringkasan dashboard KPI: capaian sasaran per area, jumlah temuan per status,
     * indeks SKM (rata-rata & konversi 0-100).
     *
     * @return array{sasaran: array, temuan: array, skm: array}
     */
    public function ringkasan(?string $periode = null): array
    {
        $this->ensureTenantContext();

        $sasaran = MutuSasaran::query()->where('is_active', true)
            ->when($periode, fn ($q) => $q->where('periode', $periode))->get();
        $perArea = [];
        foreach ($sasaran->groupBy('area') as $area => $items) {
            $perArea[$area] = round($items->avg(fn (MutuSasaran $s): float => $s->capaian()), 2);
        }

        $temuanStatus = MutuTemuan::query()
            ->select('status', DB::raw('count(*) as jml'))
            ->groupBy('status')->pluck('jml', 'status')->all();

        $skmQuery = MutuSkm::query()->when($periode, fn ($q) => $q->where('periode', $periode));
        $rataSkm = round((float) $skmQuery->avg('skor'), 2);

        return [
            'sasaran' => [
                'jumlah' => $sasaran->count(),
                'capaian_rata' => round($sasaran->avg(fn (MutuSasaran $s): float => $s->capaian()) ?? 0, 2),
                'per_area' => $perArea,
            ],
            'temuan' => [
                'terbuka' => (int) (($temuanStatus['terbuka'] ?? 0) + ($temuanStatus['tindakan'] ?? 0) + ($temuanStatus['verifikasi'] ?? 0)),
                'tutup' => (int) ($temuanStatus['tutup'] ?? 0),
                'per_status' => $temuanStatus,
            ],
            'skm' => [
                'rata' => $rataSkm,
                'indeks' => round($rataSkm / 5 * 100, 2), // konversi 0-100
                'jumlah_responden' => $skmQuery->count(),
            ],
        ];
    }

    private function pastikanTransisi(MutuTemuan $t, string $dari, string $ke): void
    {
        if ($t->status !== $dari) {
            throw new InvalidArgumentException(
                "Transisi tidak sah: '{$t->status}' → '{$ke}' (harus dari '{$dari}')."
            );
        }
    }

    private function resolveSasaran(MutuSasaran|int $s): MutuSasaran
    {
        return $s instanceof MutuSasaran ? $s : MutuSasaran::query()->findOrFail($s);
    }

    private function resolveTemuan(MutuTemuan|int $t): MutuTemuan
    {
        return $t instanceof MutuTemuan ? $t : MutuTemuan::query()->findOrFail($t);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
