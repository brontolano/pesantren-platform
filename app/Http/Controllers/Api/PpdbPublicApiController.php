<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
