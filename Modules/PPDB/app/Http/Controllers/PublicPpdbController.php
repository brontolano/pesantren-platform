<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PesertaAccountService;
use Modules\PPDB\Services\PpdbFormFieldConfigurationService;
use Modules\PPDB\Services\PpdbRegistrationNumberService;
use Modules\PPDB\Services\PpdbStatusTrackingService;

class PublicPpdbController extends Controller
{
    public function create(PpdbFormFieldConfigurationService $fieldConfigurationService): Response
    {
        $fieldConfiguration = $fieldConfigurationService->getConfiguration();

        return Inertia::render('PPDB/PublicForm', [
            'tenant' => [
                'id' => tenant()?->getTenantKey(),
                'name' => tenant()?->getAttribute('name') ?? 'Pesantren',
            ],
            'fieldConfiguration' => $fieldConfiguration,
            'formOptions' => [
                'jenisKelamin' => ['Laki-laki', 'Perempuan'],
                'jenjangAsal' => ['SD/MI', 'SMP/MTs', 'SMA/MA', 'Paket Kesetaraan'],
                'programDiminati' => ['Reguler', 'Tahfidz', 'Muadalah'],
            ],
            'registrationChecklist' => [
                'Isi biodata calon santri dan wali dengan lengkap.',
                'Pastikan nomor WhatsApp wali aktif untuk notifikasi hasil seleksi.',
                'Siapkan scan dokumen karena tahap unggah akan diaktifkan setelah nomor pendaftaran terbit.',
            ],
        ]);
    }

    public function tracking(
        Request $request,
        PpdbStatusTrackingService $trackingService
    ): Response {
        $token = (string) $request->route('token');
        $snapshot = $trackingService->getStatusSnapshot($token);

        return Inertia::render('PPDB/Portal/Tracking', [
            'tenant' => [
                'id' => tenant()?->getTenantKey(),
                'name' => tenant()?->getAttribute('name') ?? 'Pesantren',
            ],
            'snapshot' => [
                'nomorPendaftaran' => $snapshot['nomor_pendaftaran'],
                'nama' => $snapshot['nama'],
                'statusPendaftaran' => (string) $snapshot['status_pendaftaran'],
                'dokumenTerkirim' => (int) $snapshot['dokumen_terkirim'],
                'hasilUjianTercatat' => (int) $snapshot['hasil_ujian_tercatat'],
                'trackingPath' => $snapshot['tracking_path'],
            ],
            'statusCard' => [
                'badge' => $this->humanizeStatus((string) $snapshot['status_pendaftaran']),
                'title' => $this->trackingTitle((string) $snapshot['status_pendaftaran']),
                'description' => $this->trackingDescription((string) $snapshot['status_pendaftaran']),
            ],
            'cta' => [
                'loginUrl' => '/spmb/login',
                'activationUrl' => '/spmb/aktivasi?pendaftaran='.urlencode((string) $snapshot['nomor_pendaftaran']),
            ],
        ]);
    }

    public function store(
        Request $request,
        PpdbFormFieldConfigurationService $fieldConfigurationService,
        PpdbRegistrationNumberService $registrationNumberService,
        PesertaAccountService $pesertaAccountService
    )
    {
        $validated = $request->validate(
            $this->buildValidationRules($fieldConfigurationService->getConfiguration())
            + ['setuju_kebijakan' => ['accepted']],
            ['setuju_kebijakan.accepted' => 'Centang persetujuan Kebijakan Privasi Sistem untuk melanjutkan.'],
        );

        $jenisKelamin = $validated['jenisKelamin'] === 'Laki-laki' ? 'L' : 'P';

        // Nomor + insert satu transaksi (race-safe, retry saat tabrakan nomor).
        $calon = $registrationNumberService->createWithNomor([
            'tenant_id' => tenant()?->getTenantKey(),
            'nama' => $validated['nama'],
            'tanggal_lahir' => $validated['tanggalLahir'],
            'tempat_lahir' => $validated['tempatLahir'] ?? null,
            'jenis_kelamin' => $jenisKelamin,
            'asal_sekolah' => $validated['sekolahAsal'] ?? null,
            'program_diminati' => $validated['programDiminati'],
            'nama_ayah' => $validated['namaWali'],
            'no_hp_wali' => $validated['noHpWali'],
            'email_wali' => $validated['emailWali'] ?? null,
            'status_pendaftaran' => 'pending',
            'consent_kebijakan_at' => now(), // persetujuan Kebijakan Privasi Sistem (UU PDP)
            'data_ortu' => [
                'nik' => $validated['nik'] ?? null,
                'jenjang_asal' => $validated['jenjangAsal'] ?? null,
                'hubungan' => $validated['hubunganWali'] ?? null,
                'catatan_tambahan' => $validated['catatanTambahan'] ?? null,
            ],
        ]);

        $nomorPendaftaran = $calon->nomor_pendaftaran;

        // AUTH-1 (F1.1.1): terbitkan akun peserta + kirim OTP aktivasi via WA.
        // Kegagalan provisioning TIDAK boleh menggagalkan pendaftaran —
        // peserta bisa minta kode ulang di /spmb/aktivasi (atau panitia re-issue, F1.1.7).
        $infoAktivasi = ' Minta kode baru dengan tombol "Kirim ulang kode" bila belum menerima.';

        try {
            $pesertaAccountService->provisionAndSendActivationOtp($calon);
            $infoAktivasi = ' Kode aktivasi telah dikirim ke WhatsApp wali.';
        } catch (\Throwable $e) {
            // Hanya metadata — jangan log data pribadi.
            Log::warning('ppdb_peserta.provision_failed', [
                'calon_santri_id' => $calon->id,
                'error' => $e->getMessage(),
            ]);
        }

        // F1.2.2: pasca-submit langsung diarahkan ke halaman aktivasi portal
        // dengan nomor pendaftaran ter-prefill — wali tinggal memasukkan kode
        // OTP dari WhatsApp lalu membuat password.
        return redirect('/spmb/aktivasi?pendaftaran='.urlencode($nomorPendaftaran))
            ->with('success', "Pendaftaran berhasil! Nomor pendaftaran Anda: {$nomorPendaftaran}. Simpan nomor ini.{$infoAktivasi}");
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<string, array<int, string>>
     */
    private function buildValidationRules(array $fieldConfiguration): array
    {
        $definitions = [
            'nama' => ['key' => 'nama', 'rules' => ['string', 'max:255']],
            'nik' => ['key' => 'nik', 'rules' => ['string', 'max:50']],
            'tempatLahir' => ['key' => 'tempat_lahir', 'rules' => ['string', 'max:100']],
            'tanggalLahir' => ['key' => 'tanggal_lahir', 'rules' => ['date']],
            'jenisKelamin' => ['key' => 'jenis_kelamin', 'rules' => ['in:Laki-laki,Perempuan']],
            'jenjangAsal' => ['key' => 'jenjang_asal', 'rules' => ['in:SD/MI,SMP/MTs,SMA/MA,Paket Kesetaraan']],
            'sekolahAsal' => ['key' => 'sekolah_asal', 'rules' => ['string', 'max:255']],
            'namaWali' => ['key' => 'nama_wali', 'rules' => ['string', 'max:255']],
            'hubunganWali' => ['key' => 'hubungan_wali', 'rules' => ['string', 'max:100']],
            'noHpWali' => ['key' => 'no_hp_wali', 'rules' => ['string', 'max:20']],
            'emailWali' => ['key' => 'email_wali', 'rules' => ['email', 'max:255']],
            'programDiminati' => ['key' => 'program_diminati', 'rules' => ['in:Reguler,Tahfidz,Muadalah']],
            'catatanTambahan' => ['key' => 'catatan_tambahan', 'rules' => ['string', 'max:1000']],
        ];

        $rules = [];

        foreach ($definitions as $requestField => $definition) {
            $mode = $fieldConfiguration[$definition['key']]['mode'] ?? 'optional';
            $prefix = $mode === 'required' ? 'required' : 'nullable';

            $rules[$requestField] = [$prefix, ...$definition['rules']];
        }

        return $rules;
    }

    private function humanizeStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu verifikasi awal',
            'verifikasi_berkas' => 'Verifikasi berkas',
            'berkas_lengkap' => 'Berkas lengkap',
            'menunggu_pembayaran', 'pembayaran_pending' => 'Menunggu pembayaran',
            'pembayaran_terverifikasi' => 'Pembayaran terverifikasi',
            'ujian', 'ujian_seleksi' => 'Tahap ujian',
            'hasil_ujian' => 'Hasil ujian siap',
            'lulus' => 'Lulus',
            'cadangan' => 'Cadangan',
            'ditolak' => 'Belum lulus',
            'registrasi_ulang' => 'Daftar ulang selesai',
            default => 'Pendaftaran diproses',
        };
    }

    private function trackingTitle(string $status): string
    {
        return match ($status) {
            'lulus' => 'Peserta dinyatakan lulus.',
            'cadangan' => 'Peserta sedang berada di daftar cadangan.',
            'ditolak' => 'Peserta belum lulus pada gelombang ini.',
            'registrasi_ulang' => 'Daftar ulang peserta sudah tercatat.',
            default => 'Status pendaftaran masih berjalan.',
        };
    }

    private function trackingDescription(string $status): string
    {
        return match ($status) {
            'lulus' => 'Silakan masuk ke portal SPMB untuk melihat instruksi daftar ulang dan langkah berikutnya.',
            'cadangan' => 'Pantau tautan ini atau login ke portal SPMB. Panitia akan memperbarui bila ada perubahan kursi.',
            'ditolak' => 'Tautan ini hanya menampilkan status ringkas. Bila ada gelombang berikutnya, panitia akan memberi arahan lanjutan.',
            'registrasi_ulang' => 'Simpan nomor pendaftaran sampai panitia memberi arahan orientasi berikutnya.',
            default => 'Untuk melengkapi biodata, unggah dokumen, atau langkah lanjutan lain, silakan login ke portal SPMB.',
        };
    }
}
