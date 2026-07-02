<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Services\PesertaAccountService;
use Modules\PPDB\Services\PpdbRegistrationNumberService;
use Modules\PPDB\Services\PpdbStatusTrackingService;

class PublicPpdbController extends Controller
{
    public function create(): Response
    {
        // Form publik SENGAJA singkat: cukup untuk menerbitkan nomor + akun.
        // Biodata LENGKAP diisi peserta di portal /spmb setelah aktivasi.
        return Inertia::render('PPDB/PublicForm', [
            'tenant' => [
                'id' => tenant()?->getTenantKey(),
                'name' => tenant()?->getAttribute('name') ?? 'Pesantren',
            ],
            'formOptions' => [
                'jenisKelamin' => [
                    ['value' => 'L', 'label' => 'Laki-laki'],
                    ['value' => 'P', 'label' => 'Perempuan'],
                ],
                'jenjangAsal' => ['SD/MI', 'SMP/MTs', 'SMA/MA', 'Paket Kesetaraan'],
            ],
            'registrationChecklist' => [
                'Isi data ringkas calon santri & wali untuk menerbitkan nomor pendaftaran.',
                'Pastikan nomor WhatsApp wali aktif — kode aktivasi & notifikasi dikirim ke sana.',
                'Biodata lengkap & unggah dokumen dilakukan di portal SPMB setelah akun aktif.',
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
        PpdbRegistrationNumberService $registrationNumberService,
        PesertaAccountService $pesertaAccountService
    ) {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenisKelamin' => ['required', 'in:L,P'],
            'tempatLahir' => ['nullable', 'string', 'max:100'],
            'tanggalLahir' => ['required', 'date'],
            'jenjangAsal' => ['required', 'in:SD/MI,SMP/MTs,SMA/MA,Paket Kesetaraan'],
            'asalSekolah' => ['required', 'string', 'max:255'],
            'namaWali' => ['required', 'string', 'max:255'],
            'noHpWali' => ['required', 'string', 'max:20'],
            'emailWali' => ['nullable', 'email', 'max:255'],
            'setuju_kebijakan' => ['accepted'],
        ], ['setuju_kebijakan.accepted' => 'Centang persetujuan Kebijakan Privasi Sistem untuk melanjutkan.']);

        // Nomor + insert satu transaksi (race-safe, retry saat tabrakan nomor).
        // Biodata lengkap menyusul di portal /spmb (PesertaPortalController).
        $calon = $registrationNumberService->createWithNomor([
            'tenant_id' => tenant()?->getTenantKey(),
            'nama' => $validated['nama'],
            'jenis_kelamin' => $validated['jenisKelamin'],
            'tempat_lahir' => $validated['tempatLahir'] ?? null,
            'tanggal_lahir' => $validated['tanggalLahir'],
            'jenjang_asal' => $validated['jenjangAsal'],
            'asal_sekolah' => $validated['asalSekolah'],
            'nama_wali' => $validated['namaWali'],
            'no_hp_wali' => $validated['noHpWali'],
            'email_wali' => $validated['emailWali'] ?? null,
            'status_pendaftaran' => 'pending',
            'consent_kebijakan_at' => now(), // persetujuan Kebijakan Privasi Sistem (UU PDP)
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
