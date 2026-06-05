<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbRegistrationNumberService;
use Modules\PPDB\Services\PpdbWhatsappNotifier;
use Modules\PPDB\Support\PpdbPhone;

/**
 * API publik PPDB untuk website tenant (mis. asy-syifaa.com).
 * Tenant di-resolve dari domain (api.<tenant>) oleh middleware tenancy web.
 * Kontrak: docs/SPEC-API-WEBSITE-ASY-SYIFAA.md (POST /api/v1/spmb/register).
 *
 * Simpan pendaftaran + balas e-tiket + kirim notifikasi WhatsApp (n8n→WAHA).
 * Penerbitan akun & kredensial login peserta ditangani panel staf
 * (aksi "Kirim Kredensial / Reset Password" pada resource Calon Santri).
 */
class PpdbPublicApiController extends Controller
{
    public function register(Request $request, PpdbRegistrationNumberService $numbers, PpdbWhatsappNotifier $notifier): JsonResponse
    {
        if (! tenancy()->initialized || tenant() === null) {
            return response()->json(['ok' => false, 'message' => 'Tenant tidak ditemukan'], 404);
        }

        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50'],
            'nisn' => ['nullable', 'string', 'max:50'],
            // Wajib: kolom NOT NULL di tabel calon_santri (jenis_kelamin & tanggal_lahir).
            'gender' => ['required', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
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

        $participantPhone = PpdbPhone::normalize($data['participant_phone'] ?? $data['guardian_phone'] ?? $data['parent_phone'] ?? '');
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
            'no_hp_wali' => PpdbPhone::normalize($data['guardian_phone'] ?? $data['parent_phone'] ?? '') ?: null,
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

        // Username = No. HP peserta (identitas login portal). Fallback nomor pendaftaran.
        $username = $participantPhone !== '' ? $participantPhone : $nomor;

        // Notifikasi WhatsApp via n8n + WAHA (fire-and-forget, tak memblokir pendaftaran).
        $notifier->sendRegistrationConfirmation($participantPhone, $calon->nama, $nomor, $username);

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
    }
}
