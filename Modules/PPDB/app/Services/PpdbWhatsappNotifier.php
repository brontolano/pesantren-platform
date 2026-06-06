<?php

namespace Modules\PPDB\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim notifikasi WhatsApp PPDB via pipa n8n → WAHA.
 *
 * Fire-and-forget: TIDAK PERNAH melempar (try/catch + timeout pendek) agar tidak
 * menggagalkan alur pemanggil, dan TIDAK PERNAH me-log isi pesan / password
 * (kredensial tidak boleh masuk log). Endpoint via config('services.ppdb_wa.webhook').
 */
class PpdbWhatsappNotifier
{
    private function dispatch(string $phone, string $message, string $nomor, string $name): bool
    {
        if ($phone === '') {
            return false;
        }

        try {
            $response = Http::timeout(8)->post(
                config('services.ppdb_wa.webhook', 'http://n8n:5678/webhook/ppdb-wa'),
                [
                    'to' => $phone,
                    'message' => $message,
                    'eticket_no' => $nomor,
                    'student_name' => $name,
                ],
            );

            if (! $response->successful()) {
                Log::warning('ppdb_wa.send_failed', ['nomor' => $nomor, 'http_status' => $response->status()]);

                return false;
            }

            // Workflow n8n SELALU balas HTTP 200 lewat Respond node, bahkan saat
            // gateway WAHA gagal (sent:false). Jadi status 2xx saja TIDAK cukup —
            // wajib cek flag `sent` agar pemanggil (mis. aksi staf) tahu pesan
            // benar-benar terkirim atau perlu dibacakan manual.
            if ($response->json('sent') === false) {
                Log::warning('ppdb_wa.not_sent', [
                    'nomor' => $nomor,
                    'reason' => $response->json('reason'),
                    'gateway_status' => $response->json('gateway_status'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // Hanya metadata — JANGAN log $message / kredensial.
            Log::warning('ppdb_wa.send_failed', ['nomor' => $nomor, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Konfirmasi pendaftaran berhasil (dipicu otomatis setelah register). */
    public function sendRegistrationConfirmation(string $phone, string $name, string $nomor, string $username): void
    {
        $pesantren = tenant()?->name ?? 'Pondok Pesantren';
        $message = "Assalamu'alaikum Wr. Wb.\n\n"
            ."Pendaftaran PPDB *{$pesantren}* atas nama *{$name}* telah *BERHASIL* diterima. 🎉\n\n"
            ."🎫 Nomor Pendaftaran: *{$nomor}*\n"
            ."👤 Username Portal: {$username}\n\n"
            ."Simpan nomor pendaftaran ini sebagai bukti. Informasi jadwal seleksi, pengumuman, dan daftar ulang akan kami sampaikan melalui WhatsApp ini.\n\n"
            ."Jazaakumullaahu khairan.\n"
            ."— Panitia PPDB {$pesantren}";

        $this->dispatch($phone, $message, $nomor, $name);
    }

    /**
     * Kirim kredensial portal (username + password sementara + tautan login).
     * Dipakai aksi staf "Kirim Kredensial / Reset Password". Mengembalikan
     * true bila webhook menerima (2xx), agar staf tahu perlu membacakan manual.
     */
    public function sendCredentials(string $phone, string $name, string $nomor, string $username, string $password, string $loginUrl): bool
    {
        $pesantren = tenant()?->name ?? 'Pondok Pesantren';
        $message = "Assalamu'alaikum Wr. Wb.\n\n"
            ."Berikut akun *Portal PPDB {$pesantren}* atas nama *{$name}*:\n\n"
            ."🔗 Login: {$loginUrl}\n"
            ."👤 Username: {$username}\n"
            ."🔑 Password: {$password}\n\n"
            ."Demi keamanan, Anda akan diminta mengganti password saat login pertama.\n"
            ."🎫 No. Pendaftaran: {$nomor}\n\n"
            ."Jazaakumullaahu khairan.\n"
            ."— Panitia PPDB {$pesantren}";

        return $this->dispatch($phone, $message, $nomor, $name);
    }
}
