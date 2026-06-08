<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\TagihanPpdb;
use Modules\PPDB\Services\AlbaKidsRegistrationNotificationService;
use Modules\PPDB\Services\PpdbRegistrationNumberService;

class LandingRegistrationController extends Controller
{
    public function store(
        Request $request,
        PpdbRegistrationNumberService $registrationNumberService,
        AlbaKidsRegistrationNotificationService $notificationService,
    ): RedirectResponse {
        abort_unless($this->inlineAdmissionEnabled(), 404);

        $validated = $request->validate([
            'nama_anak' => ['required', 'string', 'max:255'],
            'nama_panggilan' => ['nullable', 'string', 'max:100'],
            'nik_anak' => ['nullable', 'string', 'max:50'],
            'tempat_lahir' => ['required', 'string', 'max:120'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'kelas_tujuan' => ['required', 'string', 'max:100'],
            'program_diminati' => ['required', 'string', 'max:120'],
            'asal_sekolah' => ['nullable', 'string', 'max:255'],
            'nama_ayah' => ['nullable', 'string', 'max:255'],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'nama_wali' => ['required', 'string', 'max:255'],
            'hubungan_wali' => ['required', 'string', 'max:120'],
            'no_hp_wali' => ['required', 'string', 'max:30'],
            'email_wali' => ['nullable', 'email', 'max:255'],
            'alamat' => ['required', 'string', 'max:1000'],
            'kota_domisili' => ['nullable', 'string', 'max:120'],
            'nama_pengirim_transfer' => ['required', 'string', 'max:255'],
            'bank_pengirim' => ['required', 'string', 'max:120'],
            'nominal_transfer' => ['required', 'numeric', 'min:0'],
            'tanggal_transfer' => ['required', 'date'],
            'catatan_tambahan' => ['nullable', 'string', 'max:2000'],
            'sumber_informasi' => ['nullable', 'string', 'max:255'],
            'bukti_transfer' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);

        $candidate = DB::transaction(function () use ($validated, $registrationNumberService, $request): CalonSantri {
            $nomorPendaftaran = $registrationNumberService->generate();

            $candidate = CalonSantri::create([
                'tenant_id' => tenant()?->getTenantKey(),
                'nomor_pendaftaran' => $nomorPendaftaran,
                'nama' => $validated['nama_anak'],
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'tempat_lahir' => $validated['tempat_lahir'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'nama_ayah' => $validated['nama_ayah'] ?: $validated['nama_wali'],
                'nama_ibu' => $validated['nama_ibu'],
                'no_hp_wali' => $validated['no_hp_wali'],
                'email_wali' => $validated['email_wali'] ?? null,
                'alamat' => $validated['alamat'],
                'asal_sekolah' => $validated['asal_sekolah'] ?? null,
                'program_diminati' => $validated['program_diminati'],
                'tahun_ajaran' => $this->academicYear(),
                'gelombang' => 'Landing Alba Kids',
                'status_pendaftaran' => 'menunggu_verifikasi',
                'data_ortu' => [
                    'nama_panggilan' => $validated['nama_panggilan'] ?? null,
                    'nik_anak' => $validated['nik_anak'] ?? null,
                    'kelas_tujuan' => $validated['kelas_tujuan'],
                    'nama_wali' => $validated['nama_wali'],
                    'hubungan_wali' => $validated['hubungan_wali'],
                    'kota_domisili' => $validated['kota_domisili'] ?? null,
                    'sumber_informasi' => $validated['sumber_informasi'] ?? null,
                    'catatan_tambahan' => $validated['catatan_tambahan'] ?? null,
                    'pembayaran' => [
                        'nama_pengirim_transfer' => $validated['nama_pengirim_transfer'],
                        'bank_pengirim' => $validated['bank_pengirim'],
                        'nominal_transfer' => (float) $validated['nominal_transfer'],
                        'tanggal_transfer' => $validated['tanggal_transfer'],
                    ],
                ],
            ]);

            $disk = config('filesystems.default', 'local');
            $proofPath = $request->file('bukti_transfer')->store(
                sprintf('ppdb-bukti/%s/%s', tenant()?->getTenantKey(), $candidate->getKey()),
                $disk
            );

            TagihanPpdb::create([
                'tenant_id' => tenant()?->getTenantKey(),
                'calon_santri_id' => $candidate->getKey(),
                'tipe' => 'pendaftaran_albakids',
                'nominal' => (float) $validated['nominal_transfer'],
                'jatuh_tempo' => now()->toDateString(),
                'status' => 'menunggu_verifikasi',
                'keterangan' => $this->buildPaymentSummary($validated),
                'bukti_transfer' => $proofPath,
                'bukti_diunggah_at' => now(),
            ]);

            return $candidate;
        });

        $admissionSettings = $this->admissionSettings();
        $notificationService->notify($candidate, [
            'admin_whatsapp' => $admissionSettings['admin_whatsapp'] ?? null,
            'success_message' => $admissionSettings['success_message'] ?? null,
        ]);

        $guardianName = $candidate->nama_ayah ?: $candidate->nama_ibu ?: 'Bapak/Ibu';
        $successMessage = trim((string) ($admissionSettings['success_message'] ?? ''));

        return back()->with('success', trim(sprintf(
            'Terima kasih %s. Pendaftaran %s sudah kami terima dengan nomor %s. Bukti transfer sedang diverifikasi. %s',
            $guardianName,
            $candidate->nama,
            $candidate->nomor_pendaftaran,
            $successMessage
        )));
    }

    private function inlineAdmissionEnabled(): bool
    {
        return tenant()?->getTenantKey() === 'albakids'
            || (bool) Setting::getTenantValue('admission_inline_enabled', false);
    }

    private function academicYear(): string
    {
        return (string) (Setting::getTenantStringValue('admission_academic_year', '2026/2027') ?? '2026/2027');
    }

    private function admissionSettings(): array
    {
        return [
            'admin_whatsapp' => Setting::getTenantStringValue('admission_admin_whatsapp'),
            'success_message' => Setting::getTenantStringValue('admission_success_message', 'Panitia akan segera menghubungi Anda melalui WhatsApp.'),
        ];
    }

    private function buildPaymentSummary(array $validated): string
    {
        return sprintf(
            'Pendaftaran Alba Kids | Pengirim: %s | Bank: %s | Transfer: %s | Program: %s | Kelas: %s',
            $validated['nama_pengirim_transfer'],
            $validated['bank_pengirim'],
            number_format((float) $validated['nominal_transfer'], 0, ',', '.'),
            $validated['program_diminati'],
            $validated['kelas_tujuan'],
        );
    }
}
