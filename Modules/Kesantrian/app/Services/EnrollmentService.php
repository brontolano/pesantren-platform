<?php

namespace Modules\Kesantrian\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;

/**
 * Stasiun Pendaftaran — SATU PINTU penulisan artefak enrollment (disiplin
 * LoketKas→PosKasService): foto, tanda tangan, NFC, sampel tulisan, dan
 * status langkah di santri_enrollments. File ke disk 'public' tenant,
 * disajikan via /_tenant-storage (JANGAN Storage::url — 404 di Docker).
 */
class EnrollmentService
{
    public const STEPS = ['foto', 'ttd', 'tulisan_arab', 'tulisan_latin', 'nfc', 'pin_qr', 'cetak_kartu', 'fingerprint'];

    /** Tandai status langkah (upsert). */
    public function catat(Santri $santri, string $step, string $status = 'done', ?string $filePath = null): void
    {
        if (! in_array($step, self::STEPS, true)) {
            throw new InvalidArgumentException("Langkah tidak dikenal: {$step}");
        }
        if (! in_array($status, ['pending', 'done', 'skipped'], true)) {
            throw new InvalidArgumentException("Status tidak dikenal: {$status}");
        }

        $santri->enrollments()->updateOrCreate(
            ['step' => $step],
            [
                'tenant_id' => $santri->tenant_id,
                'status' => $status,
                'file_path' => $filePath,
                'captured_by' => Auth::id(),
                'captured_at' => now(),
            ],
        );
    }

    /** @return array<string, string> step => status */
    public function status(Santri $santri): array
    {
        $tercatat = $santri->enrollments()->pluck('status', 'step')->all();

        return collect(self::STEPS)
            ->mapWithKeys(fn (string $s) => [$s => $tercatat[$s] ?? 'pending'])
            ->all();
    }

    /** Simpan foto identitas (JPEG base64 dari webcam) → santri.photo_url. */
    public function simpanFoto(Santri $santri, string $base64Jpeg): string
    {
        $bytes = $this->decodeBase64($base64Jpeg, ['image/jpeg', 'image/png']);
        $path = "enrollment/{$santri->id}/foto-".now()->format('YmdHis').'.jpg';
        Storage::disk('public')->put($path, $bytes);

        $santri->update(['photo_url' => $path]);
        $this->catat($santri, 'foto', 'done', $path);

        return $path;
    }

    /** Simpan tanda tangan: SVG master + PNG render. */
    public function simpanTtd(Santri $santri, string $svg, string $base64Png): string
    {
        if (! str_contains($svg, '<svg')) {
            throw new InvalidArgumentException('Tanda tangan SVG tidak valid.');
        }
        $stamp = now()->format('YmdHis');
        $svgPath = "enrollment/{$santri->id}/ttd-{$stamp}.svg";
        $pngPath = "enrollment/{$santri->id}/ttd-{$stamp}.png";

        Storage::disk('public')->put($svgPath, $svg);
        Storage::disk('public')->put($pngPath, $this->decodeBase64($base64Png, ['image/png']));

        $santri->update(['signature_svg_path' => $svgPath, 'signature_path' => $pngPath]);
        $this->catat($santri, 'ttd', 'done', $svgPath);

        return $svgPath;
    }

    /** Tautkan kartu NFC (UID dinormalisasi hex lowercase). */
    public function tautkanNfc(Santri $santri, string $rawUid): string
    {
        $uid = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', $rawUid));
        if (strlen($uid) < 6 || strlen($uid) > 32) {
            throw new InvalidArgumentException('UID kartu tidak valid — tempelkan ulang kartu.');
        }

        $dipakai = Santri::query()->where('nfc_uid', $uid)->where('id', '!=', $santri->id)->first();
        if ($dipakai !== null) {
            throw new InvalidArgumentException("Kartu sudah terpakai oleh {$dipakai->nama} (NIS {$dipakai->nis}).");
        }

        $santri->update(['nfc_uid' => $uid]);
        $this->catat($santri, 'nfc', 'done');

        return $uid;
    }

    /** Simpan foto formulir sampel tulisan + ground-truth per baris. */
    public function simpanSampelTulisan(Santri $santri, string $script, string $base64Jpeg, array $groundTruthLines, string $formType = 'A', string $device = 'webcam'): string
    {
        if (! in_array($script, ['arab', 'latin'], true)) {
            throw new InvalidArgumentException('Script harus arab|latin.');
        }
        $bytes = $this->decodeBase64($base64Jpeg, ['image/jpeg', 'image/png']);
        $path = "handwriting-samples/{$santri->id}/{$script}-{$formType}v1-".now()->format('YmdHis').'.jpg';
        Storage::disk('public')->put($path, $bytes);

        $santri->handwritingSamples()->create([
            'tenant_id' => $santri->tenant_id,
            'script' => $script,
            'form_type' => $formType,
            'form_version' => 'v1',
            'file_path' => $path,
            'ground_truth' => array_values(array_map('strval', $groundTruthLines)),
            'device' => $device,
            'status_qc' => 'ok',
            'captured_at' => now(),
        ]);

        $this->catat($santri, $script === 'arab' ? 'tulisan_arab' : 'tulisan_latin', 'done', $path);

        return $path;
    }

    private function decodeBase64(string $dataUri, array $allowedMimes): string
    {
        $payload = $dataUri;
        if (preg_match('#^data:([a-z0-9/+.-]+);base64,#i', $dataUri, $m)) {
            if (! in_array(strtolower($m[1]), $allowedMimes, true)) {
                throw new InvalidArgumentException('Tipe berkas tidak diizinkan.');
            }
            $payload = substr($dataUri, strlen($m[0]));
        }

        $bytes = base64_decode($payload, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('Berkas tidak valid atau terlalu besar (maks 8MB).');
        }

        return $bytes;
    }
}
