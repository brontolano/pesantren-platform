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
            'tempat_lahir' => ['required', 'string', 'max:120'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'child_order' => ['nullable', 'string', 'max:50'],
            'kelas_tujuan' => ['nullable', 'string', 'max:100'],
            'program_diminati' => ['nullable', 'string', 'max:120'],
            'nama_ayah' => ['required', 'string', 'max:255'],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:255'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:255'],
            'no_hp_ayah' => ['nullable', 'string', 'max:30'],
            'no_hp_ibu' => ['nullable', 'string', 'max:30'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'hubungan_wali' => ['nullable', 'string', 'max:120'],
            'no_hp_wali' => ['nullable', 'string', 'max:30'],
            'alamat' => ['required', 'string', 'max:1000'],
            'nama_pengirim_transfer' => ['nullable', 'string', 'max:255'],
            'bank_pengirim' => ['nullable', 'string', 'max:120'],
            'nominal_transfer' => ['nullable', 'numeric', 'min:0'],
            'tanggal_transfer' => ['nullable', 'date'],
            'bukti_transfer' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'foto_kk' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'foto_akta' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);

        // Pastikan semua field opsional selalu hadir sebagai key. Form kustom
        // (mis. Alba Kids) tidak mengirim sebagian field nullable → tanpa ini
        // akses `$validated['nama_wali']` dll. melempar "Undefined array key" → 500.
        $validated = array_merge([
            'child_order' => null,
            'kelas_tujuan' => null,
            'program_diminati' => null,
            'pekerjaan_ayah' => null,
            'pekerjaan_ibu' => null,
            'no_hp_ayah' => null,
            'no_hp_ibu' => null,
            'nama_wali' => null,
            'hubungan_wali' => null,
            'no_hp_wali' => null,
            'nama_pengirim_transfer' => null,
            'bank_pengirim' => null,
            'nominal_transfer' => null,
            'tanggal_transfer' => null,
        ], $validated);

        $candidate = DB::transaction(function () use ($validated, $registrationNumberService, $request): CalonSantri {
            $guardianName = $validated['nama_wali'] ?: $validated['nama_ibu'];
            $guardianPhone = $validated['no_hp_wali'] ?: ($validated['no_hp_ibu'] ?: ($validated['no_hp_ayah'] ?: ''));
            $program = $validated['program_diminati'] ?: 'Preschool';
            $kelas = $validated['kelas_tujuan'] ?: 'Kelompok Bermain';
            $bankPengirim = $validated['bank_pengirim'] ?: (Setting::getTenantStringValue('admission_bank_name', 'Bank Mandiri') ?? 'Bank Mandiri');
            $nominalTransfer = isset($validated['nominal_transfer'])
                ? (float) $validated['nominal_transfer']
                : $this->defaultTransferAmount();
            $tanggalTransfer = $validated['tanggal_transfer'] ?? now()->toDateString();
            $namaPengirimTransfer = $validated['nama_pengirim_transfer'] ?: $guardianName;

            // Nomor + insert satu transaksi via service (race-safe; transaksi luar
            // ini menjadikannya savepoint — lock bertahan sampai commit luar).
            $candidate = $registrationNumberService->createWithNomor([
                'tenant_id' => tenant()?->getTenantKey(),
                'nama' => $validated['nama_anak'],
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'tempat_lahir' => $validated['tempat_lahir'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'nama_ayah' => $validated['nama_ayah'],
                'nama_ibu' => $validated['nama_ibu'],
                'no_hp_wali' => $guardianPhone,
                'email_wali' => null,
                'alamat' => $validated['alamat'],
                'asal_sekolah' => null,
                'program_diminati' => $program,
                'tahun_ajaran' => $this->academicYear(),
                'gelombang' => 'Landing Alba Kids',
                'status_pendaftaran' => 'menunggu_verifikasi',
                'data_ortu' => [
                    'child_order' => $validated['child_order'] ?? null,
                    'kelas_tujuan' => $kelas,
                    'nama_wali' => $guardianName,
                    'hubungan_wali' => $validated['hubungan_wali'] ?: 'Ibu',
                    'pekerjaan_ayah' => $validated['pekerjaan_ayah'] ?? null,
                    'pekerjaan_ibu' => $validated['pekerjaan_ibu'] ?? null,
                    'no_hp_ayah' => $validated['no_hp_ayah'] ?? null,
                    'no_hp_ibu' => $validated['no_hp_ibu'] ?? null,
                    'pembayaran' => [
                        'nama_pengirim_transfer' => $namaPengirimTransfer,
                        'bank_pengirim' => $bankPengirim,
                        'nominal_transfer' => $nominalTransfer,
                        'tanggal_transfer' => $tanggalTransfer,
                    ],
                ],
            ]);

            $disk = config('filesystems.default', 'local');
            $proofPath = $request->file('bukti_transfer')->store(
                sprintf('ppdb-bukti/%s/%s', tenant()?->getTenantKey(), $candidate->getKey()),
                $disk
            );

            // Dokumen tambahan: foto Kartu Keluarga & Akta Kelahiran (opsional di server,
            // wajib di form V2). Disimpan hanya bila diunggah agar form landing lain tetap jalan.
            $docDir = sprintf('ppdb-dokumen/%s/%s', tenant()?->getTenantKey(), $candidate->getKey());
            $dokumen = [];
            if ($request->hasFile('foto_kk')) {
                $dokumen['kk'] = $request->file('foto_kk')->store($docDir, $disk);
            }
            if ($request->hasFile('foto_akta')) {
                $dokumen['akta'] = $request->file('foto_akta')->store($docDir, $disk);
            }
            if ($dokumen !== []) {
                $candidate->update([
                    'data_ortu' => array_merge($candidate->data_ortu ?? [], ['dokumen' => $dokumen]),
                ]);
            }

            TagihanPpdb::create([
                'tenant_id' => tenant()?->getTenantKey(),
                'calon_santri_id' => $candidate->getKey(),
                'tipe' => 'pendaftaran_albakids',
                'nominal' => $nominalTransfer,
                'jatuh_tempo' => now()->toDateString(),
                'status' => 'menunggu_verifikasi',
                'keterangan' => $this->buildPaymentSummary([
                    ...$validated,
                    'nama_pengirim_transfer' => $namaPengirimTransfer,
                    'bank_pengirim' => $bankPengirim,
                    'nominal_transfer' => $nominalTransfer,
                    'program_diminati' => $program,
                    'kelas_tujuan' => $kelas,
                ]),
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
            'admin_whatsapp' => Setting::getTenantStringValue('admission_admin_whatsapp', '+6282260901399'),
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

    private function defaultTransferAmount(): float
    {
        $value = Setting::getTenantStringValue('admission_transfer_amount', '1885000') ?? '1885000';
        $normalized = preg_replace('/[^\d]/', '', $value);

        return is_string($normalized) && $normalized !== '' ? (float) $normalized : 1885000.0;
    }
}
