<?php

namespace Modules\Notifikasi\Services;

use Illuminate\Support\Facades\Http;
use Modules\Notifikasi\Support\NotifikasiSettings;
use Throwable;

/**
 * Health-check sesi WAHA (WhatsApp HTTP API). Memanggil
 * GET {waha_base_url}/api/sessions?all=true lalu memeriksa apakah sesi
 * (default: config notifikasi.waha_session) berstatus 'WORKING'.
 *
 * PRINSIP: timeout pendek + error DITELAN. Health-check yang gagal TIDAK
 * boleh mengganggu pengiriman notifikasi — kegagalan probe dilaporkan sebagai
 * "tidak sehat" (healthy=false), bukan exception.
 */
class WahaHealthService
{
    /**
     * @return array{
     *   configured: bool,
     *   healthy: bool,
     *   status: ?string,
     *   session: string,
     *   error: ?string
     * }
     */
    public function check(int $timeout = 5): array
    {
        $session = (string) config('notifikasi.waha_session', 'default');
        $baseUrl = config('notifikasi.waha_base_url');

        // WAHA langsung tak dikonfigurasi (pakai pipa n8n) → bukan "sakit",
        // hanya "tak dapat diperiksa dari sini". configured=false.
        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            return [
                'configured' => false,
                'healthy' => false,
                'status' => null,
                'session' => $session,
                'error' => 'WAHA_BASE_URL belum diatur (notifikasi lewat pipa n8n).',
            ];
        }

        try {
            $client = Http::acceptJson()->timeout(max(1, $timeout));

            if ($apiKey = NotifikasiSettings::wahaApiKey()) {
                $client = $client->withHeaders(['X-Api-Key' => $apiKey]);
            }

            $response = $client
                ->baseUrl(rtrim($baseUrl, '/'))
                ->get('/api/sessions', ['all' => 'true']);

            if (! $response->successful()) {
                return [
                    'configured' => true,
                    'healthy' => false,
                    'status' => null,
                    'session' => $session,
                    'error' => 'WAHA membalas HTTP '.$response->status().'.',
                ];
            }

            $sessions = $response->json();
            $status = $this->extractStatus(is_array($sessions) ? $sessions : [], $session);

            return [
                'configured' => true,
                'healthy' => $status === 'WORKING',
                'status' => $status,
                'session' => $session,
                'error' => $status === null
                    ? "Sesi '{$session}' tidak ditemukan di WAHA."
                    : null,
            ];
        } catch (Throwable $e) {
            // Timeout / koneksi gagal / respons rusak → tidak sehat, TANPA throw.
            return [
                'configured' => true,
                'healthy' => false,
                'status' => null,
                'session' => $session,
                'error' => 'Gagal menghubungi WAHA: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Ambil status sesi bernama $session dari payload /api/sessions.
     * Bentuk WAHA: [ {"name":"default","status":"WORKING", ...}, ... ].
     */
    private function extractStatus(array $sessions, string $session): ?string
    {
        foreach ($sessions as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = $row['name'] ?? $row['session'] ?? null;

            if ($name === $session && isset($row['status']) && is_string($row['status'])) {
                return $row['status'];
            }
        }

        return null;
    }
}
