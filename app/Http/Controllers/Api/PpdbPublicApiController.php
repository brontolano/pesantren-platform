<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbRegistrationNumberService;

/**
 * API publik PPDB untuk website tenant (mis. asy-syifaa.com).
 * Tenant di-resolve dari domain (api.<tenant>) oleh middleware tenancy web.
 * Kontrak: docs/SPEC-API-WEBSITE-ASY-SYIFAA.md (POST /api/v1/spmb/register).
 *
 * Fase ini = MINIMAL: simpan pendaftaran + balas e-tiket. Akun login = fase berikutnya.
 */
class PpdbPublicApiController extends Controller
{
    public function register(Request $request, PpdbRegistrationNumberService $numbers): JsonResponse
    {
        if (! tenancy()->initialized || tenant() === null) {
            return response()->json(['ok' => false, 'message' => 'Tenant tidak ditemukan'], 404);
        }

        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50'],
            'nisn' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'origin_school' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'village_code' => ['nullable', 'string', 'max:50'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:30'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'participant_phone' => ['nullable', 'string', 'max:30'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try { // TEMP-DEBUG: surface exact exception for the register 500
        $participantPhone = $this->normalizePhone($data['participant_phone'] ?? $data['guardian_phone'] ?? $data['parent_phone'] ?? '');
        $nomor = $numbers->generate();
        $token = Str::random(40);

        $calon = CalonSantri::create([
            'nomor_pendaftaran' => $nomor,
            'tracking_token' => $token,
            'tracking_token_generated_at' => now(),
            'nama' => $data['student_name'],
            'tanggal_lahir' => $data['birth_date'] ?? null,
            'tempat_lahir' => $data['birth_place'] ?? null,
            'jenis_kelamin' => $data['gender'] ?? null,
            'nama_ayah' => $data['parent_name'] ?? null,
            'no_hp_wali' => $this->normalizePhone($data['guardian_phone'] ?? $data['parent_phone'] ?? '') ?: null,
            'alamat' => $data['address'] ?? null,
            'asal_sekolah' => $data['origin_school'] ?? null,
            'program_diminati' => 'Reguler',
            'status_pendaftaran' => 'pending',
            'data_ortu' => [
                'nik' => $data['nik'] ?? null,
                'nisn' => $data['nisn'] ?? null,
                'village_code' => $data['village_code'] ?? null,
                'parent_phone' => $data['parent_phone'] ?? null,
                'participant_phone' => $participantPhone,
                'academic_year' => $data['academic_year'] ?? null,
                'source' => $data['source'] ?? 'website',
                'notes' => $data['notes'] ?? null,
            ],
        ]);

        // Username = No. HP peserta (calon login di fase akun nanti). Fallback nomor pendaftaran.
        $username = $participantPhone !== '' ? $participantPhone : $nomor;

        // Notifikasi WhatsApp via n8n + WAHA (fire-and-forget, tak memblokir pendaftaran).
        $this->notifyWhatsapp($participantPhone, $calon->nama, $nomor, $username);

        return response()->json([
            'ok' => true,
            'success' => true,
            'data' => [
                'id' => $calon->id,
                'eticket_no' => $nomor,
                'registration_number' => $nomor,
                'username' => $username,
                'erp_username' => $username,
                'tracking_token' => $token,
            ],
            'account' => ['username' => $username],
        ], 201);
        } catch (\Throwable $e) { // TEMP-DEBUG
            return response()->json(['ok' => false, 'debug' => [
                'type' => get_class($e),
                'msg' => $e->getMessage(),
                'where' => basename($e->getFile()).':'.$e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->map(fn ($f) => (isset($f['file']) ? basename($f['file']) : '?').':'.($f['line'] ?? '?').' '.($f['class'] ?? '').($f['type'] ?? '').($f['function'] ?? ''))->all(),
            ]], 500);
        }
    }

    /**
     * Kirim notifikasi WhatsApp via n8n → WAHA. Fire-and-forget:
     * kegagalan WA TIDAK boleh menggagalkan pendaftaran (try-catch + timeout pendek).
     * Endpoint internal: http://n8n:5678/webhook/ppdb-wa (override via PPDB_WA_WEBHOOK_URL).
     */
    private function notifyWhatsapp(string $phone, string $name, string $nomor, string $username): void
    {
        if ($phone === '') {
            return;
        }

        $pesantren = tenant()?->name ?? 'Pondok Pesantren';
        $message = "Assalamu'alaikum Wr. Wb.\n\n"
            ."Pendaftaran PPDB *{$pesantren}* atas nama *{$name}* telah *BERHASIL* diterima. 🎉\n\n"
            ."🎫 Nomor Pendaftaran: *{$nomor}*\n"
            ."👤 Username Portal: {$username}\n\n"
            ."Simpan nomor pendaftaran ini sebagai bukti. Informasi jadwal seleksi, pengumuman, dan daftar ulang akan kami sampaikan melalui WhatsApp ini.\n\n"
            ."Jazaakumullaahu khairan.\n"
            ."— Panitia PPDB {$pesantren}";

        try {
            Http::timeout(8)->post(
                config('services.ppdb_wa.webhook', 'http://n8n:5678/webhook/ppdb-wa'),
                [
                    'to' => $phone,
                    'message' => $message,
                    'eticket_no' => $nomor,
                    'student_name' => $name,
                ],
            );
        } catch (\Throwable) {
            // Senyap: pendaftaran tetap sukses meski notifikasi WA gagal terkirim.
        }
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '+62')) {
            $digits = '62'.substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
