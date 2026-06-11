<?php

namespace Modules\Pembiayaan\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Pembiayaan\Models\AreaPiket;
use Modules\Pembiayaan\Models\CeklisPiket;
use Modules\Pembiayaan\Models\LaporanKerusakan;

/**
 * WP-33 — Lapor Kerusakan & Ceklis Piket (Sarpras).
 *
 * - Foto laporan WAJIB pdf/jpg/png (stored-XSS) → disimpan di dir
 *   'kerusakan' (terdaftar sebagai direktori sensitif di routes/tenant.php,
 *   serve via /_tenant-storage hanya untuk pengguna login).
 * - Status kerusakan SEARAH: masuk → dikerjakan → selesai.
 * - Biaya: HANYA dicatat sebagai info — pengeluaran dibuat manual via modul
 *   Keuangan (hint di panel), TIDAK auto-posting (pelajaran loop keuangan).
 */
class SarprasService
{
    public const EXT_FOTO = ['pdf', 'jpg', 'jpeg', 'png'];

    public const MIME_FOTO = ['application/pdf', 'image/jpeg', 'image/png'];

    /** Transisi status yang diizinkan (searah, tanpa mundur/lompat). */
    private const TRANSISI = [
        'masuk' => ['dikerjakan'],
        'dikerjakan' => ['selesai'],
        'selesai' => [],
    ];

    public function laporKerusakan(User $pelapor, array $data, UploadedFile $foto): LaporanKerusakan
    {
        $ext = strtolower($foto->getClientOriginalExtension());
        $mime = (string) $foto->getMimeType();

        if (! in_array($ext, self::EXT_FOTO, true) || ! in_array($mime, self::MIME_FOTO, true)) {
            throw new InvalidArgumentException('Foto wajib berformat pdf/jpg/png.');
        }

        // hashName → nama acak, ekstensi mengikuti file; cegah path traversal.
        $path = $foto->storeAs('kerusakan', $foto->hashName(), 'public');

        return LaporanKerusakan::create([
            'tenant_id' => $pelapor->tenant_id,
            'pelapor_id' => $pelapor->id,
            'lokasi' => $data['lokasi'],
            'deskripsi' => $data['deskripsi'],
            'foto_path' => $path,
            'status' => 'masuk',
        ]);
    }

    /**
     * Ubah status laporan — hanya transisi searah yang sah.
     *
     * @throws InvalidArgumentException
     */
    public function ubahStatus(LaporanKerusakan $laporan, string $status, array $opts = []): LaporanKerusakan
    {
        if (! in_array($status, LaporanKerusakan::STATUS, true)) {
            throw new InvalidArgumentException("Status tidak valid: {$status}");
        }

        if (! in_array($status, self::TRANSISI[$laporan->status] ?? [], true)) {
            throw new InvalidArgumentException(
                "Transisi status {$laporan->status} → {$status} tidak diizinkan (siklus: masuk → dikerjakan → selesai)."
            );
        }

        $laporan->update(array_filter([
            'status' => $status,
            'biaya' => $opts['biaya'] ?? null,
            'catatan' => $opts['catatan'] ?? null,
        ], fn ($v) => $v !== null));

        return $laporan->fresh();
    }

    /**
     * Ceklis piket via scan kode QR area (keyboard-wedge).
     * Idempoten per (area, petugas, tanggal) — scan ulang meng-update.
     *
     * @throws InvalidArgumentException jika kode QR tidak dikenal/nonaktif
     */
    public function scanPiket(User $petugas, string $kodeQr, string $status, ?string $catatan = null): CeklisPiket
    {
        if (! in_array($status, CeklisPiket::STATUS, true)) {
            throw new InvalidArgumentException("Status piket tidak valid: {$status}");
        }

        $area = AreaPiket::query()->where('kode_qr', $kodeQr)->where('aktif', true)->first();

        if ($area === null) {
            throw new InvalidArgumentException('Kode QR area piket tidak dikenal atau nonaktif.');
        }

        return CeklisPiket::updateOrCreate(
            [
                'area_id' => $area->id,
                'petugas_id' => $petugas->id,
                'tanggal' => now()->toDateString(),
            ],
            [
                'tenant_id' => $area->tenant_id,
                'jam' => now()->format('H:i:s'),
                'status' => $status,
                'catatan' => $catatan,
            ]
        );
    }

    /** Ringkasan piket hari ini per area (untuk halaman panel). */
    public function piketHariIni(): array
    {
        $areas = AreaPiket::query()->where('aktif', true)->orderBy('nama')->get();
        $ceklis = CeklisPiket::query()
            ->whereDate('tanggal', now()->toDateString())
            ->with(['area', 'petugas'])
            ->orderByDesc('jam')
            ->get()
            ->groupBy('area_id');

        return $areas->map(fn (AreaPiket $area) => [
            'area' => $area,
            'ceklis' => $ceklis->get($area->id, collect()),
        ])->all();
    }

    public function hapusFoto(LaporanKerusakan $laporan): void
    {
        if ($laporan->foto_path && Storage::disk('public')->exists($laporan->foto_path)) {
            Storage::disk('public')->delete($laporan->foto_path);
        }
    }
}
