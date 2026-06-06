<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbFormFieldConfigurationService;
use Modules\PPDB\Services\PpdbStatusTrackingService;
use Modules\PPDB\Support\PpdbDocumentTypes;

class PesertaPortalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PpdbFormFieldConfigurationService $fieldConfigurationService,
        private readonly PpdbStatusTrackingService $statusTrackingService,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        $calon = $this->resolveOwnedCandidate($request->user());
        $fieldConfiguration = $this->fieldConfigurationService->getConfiguration();
        $tracking = $this->statusTrackingService->issueTrackingLink($calon);

        return Inertia::render('PPDB/Portal/Dashboard', [
            'candidate' => $this->buildCandidatePayload($calon, $fieldConfiguration),
            'fieldConfiguration' => $fieldConfiguration,
            'statusCard' => $this->buildStatusCard($calon, $tracking['tracking_url']),
            'stepper' => $this->buildStepper($calon),
            'checklist' => $this->buildChecklist($calon, $fieldConfiguration),
            'mainAction' => $this->buildMainAction($calon, $fieldConfiguration),
            'tracking' => [
                'url' => $tracking['tracking_url'],
                'path' => $tracking['tracking_path'],
                'token' => $tracking['tracking_token'],
            ],
        ]);
    }

    /**
     * Halaman Berkas — checklist kelengkapan dokumen peserta (F2/DOC-4).
     * Hanya-baca: menampilkan tipe dokumen wajib/opsional + status verifikasi
     * tiap dokumen yang sudah diunggah. (Aksi unggah ditangani alur upload tim.)
     */
    public function berkas(Request $request): Response
    {
        $calon = $this->resolveOwnedCandidate($request->user());
        $uploaded = $calon->dokumen()->get()->keyBy('jenis_dokumen');

        $items = [];
        foreach (PpdbDocumentTypes::all() as $jenis => $meta) {
            $doc = $uploaded->get($jenis);

            $items[] = [
                'jenis' => $jenis,
                'label' => $meta['label'],
                'wajib' => $meta['required'],
                'terunggah' => $doc !== null,
                'status' => $doc?->effectiveStatus(),
                'statusLabel' => $doc?->statusLabel(),
                'catatan' => $doc?->catatan_verifikasi,
            ];
        }

        $kurangWajib = PpdbDocumentTypes::missingRequired($uploaded->keys()->all());

        return Inertia::render('PPDB/Portal/Berkas', [
            'nomorPendaftaran' => (string) $calon->nomor_pendaftaran,
            'items' => $items,
            'kurangWajib' => array_map(
                static fn (string $k): string => PpdbDocumentTypes::label($k),
                $kurangWajib,
            ),
            'lengkapWajib' => $kurangWajib === [],
        ]);
    }

    public function updateBiodata(Request $request): RedirectResponse
    {
        $calon = $this->resolveOwnedCandidate($request->user());

        $this->authorize('update', $calon);

        $fieldConfiguration = $this->fieldConfigurationService->getConfiguration();
        $validated = $request->validate($this->buildValidationRules($fieldConfiguration));
        $validated = $this->stripHiddenFields($validated, $fieldConfiguration);

        $calon->update([
            'nama' => $validated['nama'] ?? $calon->nama,
            'tanggal_lahir' => $validated['tanggalLahir'] ?? $calon->tanggal_lahir?->format('Y-m-d'),
            'tempat_lahir' => $validated['tempatLahir'] ?? null,
            'jenis_kelamin' => $this->mapJenisKelamin($validated['jenisKelamin'] ?? null, $calon->jenis_kelamin),
            'asal_sekolah' => $validated['sekolahAsal'] ?? null,
            'program_diminati' => $validated['programDiminati'] ?? $calon->program_diminati,
            'nama_ayah' => $validated['namaWali'] ?? null,
            'no_hp_wali' => $validated['noHpWali'] ?? null,
            'email_wali' => $validated['emailWali'] ?? null,
            'data_ortu' => [
                'nik' => $validated['nik'] ?? null,
                'jenjang_asal' => $validated['jenjangAsal'] ?? null,
                'hubungan' => $validated['hubunganWali'] ?? null,
                'catatan_tambahan' => $validated['catatanTambahan'] ?? null,
            ],
        ]);

        return back()->with('success', 'Biodata pendaftaran berhasil diperbarui.');
    }

    private function resolveOwnedCandidate(?User $user): CalonSantri
    {
        if ($user === null) {
            throw ValidationException::withMessages([
                'auth' => 'Silakan masuk terlebih dahulu.',
            ]);
        }

        $calon = CalonSantri::query()
            ->where('user_id', $user->id)
            ->first();

        if ($calon === null) {
            throw ValidationException::withMessages([
                'auth' => 'Data pendaftaran Anda belum ditemukan. Silakan hubungi panitia.',
            ]);
        }

        $this->authorize('view', $calon);

        return $calon;
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<string, mixed>
     */
    private function buildCandidatePayload(CalonSantri $calon, array $fieldConfiguration): array
    {
        $dataOrtu = is_array($calon->data_ortu) ? $calon->data_ortu : [];

        return [
            'id' => $calon->id,
            'nomorPendaftaran' => $calon->nomor_pendaftaran,
            'statusPendaftaran' => (string) $calon->status_pendaftaran,
            'nama' => (string) $calon->nama,
            'nik' => (string) ($dataOrtu['nik'] ?? ''),
            'tempatLahir' => (string) ($calon->tempat_lahir ?? ''),
            'tanggalLahir' => $calon->tanggal_lahir?->format('Y-m-d') ?? '',
            'jenisKelamin' => $this->formatJenisKelamin($calon->jenis_kelamin),
            'jenjangAsal' => (string) ($dataOrtu['jenjang_asal'] ?? ''),
            'sekolahAsal' => (string) ($calon->asal_sekolah ?? ''),
            'namaWali' => (string) ($calon->nama_ayah ?? ''),
            'hubunganWali' => (string) ($dataOrtu['hubungan'] ?? ''),
            'noHpWali' => (string) ($calon->no_hp_wali ?? ''),
            'emailWali' => (string) ($calon->email_wali ?? ''),
            'programDiminati' => (string) ($calon->program_diminati ?? ''),
            'catatanTambahan' => (string) ($dataOrtu['catatan_tambahan'] ?? ''),
            'canEditBiodata' => $this->isEditableStatus((string) $calon->status_pendaftaran),
            'completion' => [
                'filled' => $this->countCompletedRequiredFields($calon, $fieldConfiguration),
                'total' => $this->countRequiredFields($fieldConfiguration),
            ],
        ];
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<int, array<string, mixed>>
     */
    private function buildChecklist(CalonSantri $calon, array $fieldConfiguration): array
    {
        $requiredTotal = $this->countRequiredFields($fieldConfiguration);
        $requiredFilled = $this->countCompletedRequiredFields($calon, $fieldConfiguration);
        $status = (string) $calon->status_pendaftaran;

        return [
            [
                'title' => 'Lengkapi biodata peserta dan wali',
                'done' => $requiredFilled >= $requiredTotal,
                'description' => $requiredFilled >= $requiredTotal
                    ? 'Semua field wajib pada tahap F1 sudah terisi.'
                    : "Baru {$requiredFilled} dari {$requiredTotal} field wajib yang terisi.",
            ],
            [
                'title' => 'Simpan nomor pendaftaran dan link status',
                'done' => ! empty($calon->nomor_pendaftaran) && ! empty($calon->tracking_token),
                'description' => 'Nomor ini dipakai untuk aktivasi, login, dan pemantauan cepat.',
            ],
            [
                'title' => 'Pantau instruksi panitia berikutnya',
                'done' => in_array($status, ['lulus', 'cadangan', 'ditolak', 'registrasi_ulang'], true),
                'description' => $this->statusHelperText($status),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildStepper(CalonSantri $calon): array
    {
        $current = $this->resolveStepIndex((string) $calon->status_pendaftaran);
        $steps = [
            ['title' => 'Daftar', 'hint' => 'Formulir awal sudah masuk.'],
            ['title' => 'Berkas', 'hint' => 'Tahap unggah dan verifikasi berkas.'],
            ['title' => 'Pembayaran', 'hint' => 'Biaya pendaftaran dan verifikasi transfer.'],
            ['title' => 'Ujian', 'hint' => 'Jadwal ujian dan kartu peserta.'],
            ['title' => 'Pengumuman', 'hint' => 'Keputusan panitia untuk pendaftaran Anda.'],
            ['title' => 'Daftar Ulang', 'hint' => 'Konfirmasi akhir hingga resmi menjadi santri.'],
        ];

        return collect($steps)->map(function (array $step, int $index) use ($current): array {
            return [
                ...$step,
                'state' => $index < $current ? 'done' : ($index === $current ? 'current' : 'upcoming'),
            ];
        })->all();
    }

    private function buildStatusCard(CalonSantri $calon, string $trackingUrl): array
    {
        $status = (string) $calon->status_pendaftaran;

        return [
            'badge' => $this->humanizeStatus($status),
            'title' => $this->statusTitle($status),
            'description' => $this->statusDescription($status),
            'trackingUrl' => $trackingUrl,
        ];
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<string, string>
     */
    private function buildMainAction(CalonSantri $calon, array $fieldConfiguration): array
    {
        $requiredTotal = $this->countRequiredFields($fieldConfiguration);
        $requiredFilled = $this->countCompletedRequiredFields($calon, $fieldConfiguration);
        $status = (string) $calon->status_pendaftaran;

        if ($requiredFilled < $requiredTotal && $this->isEditableStatus($status)) {
            return [
                'label' => 'Lengkapi biodata sekarang',
                'type' => 'form',
                'helper' => 'Isi dulu data wajib agar panitia mudah memverifikasi.',
            ];
        }

        return [
            'label' => 'Pantau status pendaftaran',
            'type' => 'tracking',
            'helper' => $this->statusHelperText($status),
        ];
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

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<string, mixed>
     */
    private function stripHiddenFields(array $validated, array $fieldConfiguration): array
    {
        $requestKeyMap = [
            'nama' => 'nama',
            'nik' => 'nik',
            'tempatLahir' => 'tempat_lahir',
            'tanggalLahir' => 'tanggal_lahir',
            'jenisKelamin' => 'jenis_kelamin',
            'jenjangAsal' => 'jenjang_asal',
            'sekolahAsal' => 'sekolah_asal',
            'namaWali' => 'nama_wali',
            'hubunganWali' => 'hubungan_wali',
            'noHpWali' => 'no_hp_wali',
            'emailWali' => 'email_wali',
            'programDiminati' => 'program_diminati',
            'catatanTambahan' => 'catatan_tambahan',
        ];

        foreach ($requestKeyMap as $requestKey => $configKey) {
            if (($fieldConfiguration[$configKey]['mode'] ?? 'optional') === 'hidden') {
                unset($validated[$requestKey]);
            }
        }

        return $validated;
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     */
    private function countRequiredFields(array $fieldConfiguration): int
    {
        return collect($fieldConfiguration)
            ->where('mode', 'required')
            ->count();
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     */
    private function countCompletedRequiredFields(CalonSantri $calon, array $fieldConfiguration): int
    {
        $dataOrtu = is_array($calon->data_ortu) ? $calon->data_ortu : [];

        $values = [
            'nama' => $calon->nama,
            'nik' => $dataOrtu['nik'] ?? null,
            'tempat_lahir' => $calon->tempat_lahir,
            'tanggal_lahir' => $calon->tanggal_lahir?->format('Y-m-d'),
            'jenis_kelamin' => $calon->jenis_kelamin,
            'jenjang_asal' => $dataOrtu['jenjang_asal'] ?? null,
            'sekolah_asal' => $calon->asal_sekolah,
            'nama_wali' => $calon->nama_ayah,
            'hubungan_wali' => $dataOrtu['hubungan'] ?? null,
            'no_hp_wali' => $calon->no_hp_wali,
            'email_wali' => $calon->email_wali,
            'program_diminati' => $calon->program_diminati,
            'catatan_tambahan' => $dataOrtu['catatan_tambahan'] ?? null,
        ];

        return collect($fieldConfiguration)
            ->filter(fn (array $field): bool => $field['mode'] === 'required')
            ->filter(function (array $field, string $key) use ($values): bool {
                $value = $values[$key] ?? null;

                return is_string($value)
                    ? trim($value) !== ''
                    : $value !== null;
            })
            ->count();
    }

    private function resolveStepIndex(string $status): int
    {
        return match ($status) {
            'draft', 'pending', 'verifikasi_berkas' => 0,
            'berkas_lengkap' => 1,
            'menunggu_pembayaran', 'pembayaran_pending', 'pembayaran_terverifikasi' => 2,
            'ujian', 'ujian_seleksi', 'hasil_ujian' => 3,
            'lulus', 'cadangan', 'ditolak' => 4,
            'registrasi_ulang' => 5,
            default => 0,
        };
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

    private function statusTitle(string $status): string
    {
        return match ($status) {
            'lulus' => 'Alhamdulillah, Anda dinyatakan lulus.',
            'cadangan' => 'Anda masuk daftar cadangan.',
            'ditolak' => 'Status pendaftaran Anda belum lulus.',
            'registrasi_ulang' => 'Daftar ulang Anda sudah masuk.',
            default => 'Pendaftaran Anda sedang diproses panitia.',
        };
    }

    private function statusDescription(string $status): string
    {
        return match ($status) {
            'lulus' => 'Siapkan langkah daftar ulang sesuai instruksi yang akan dibuka panitia.',
            'cadangan' => 'Pantau portal ini secara berkala. Panitia akan memberi kabar bila ada kursi tersedia.',
            'ditolak' => 'Terima kasih sudah mendaftar. Bila ada gelombang berikutnya, panitia akan menginformasikan lebih lanjut.',
            'registrasi_ulang' => 'Data akhir Anda sudah tercatat. Simpan nomor pendaftaran sampai panitia memberi arahan berikutnya.',
            default => 'Lengkapi biodata yang masih kosong dan pantau instruksi tahap berikutnya dari panitia.',
        };
    }

    private function statusHelperText(string $status): string
    {
        return match ($status) {
            'lulus' => 'Panitia sudah menetapkan hasil. Tahap berikutnya adalah daftar ulang.',
            'cadangan' => 'Status Anda masih menunggu keputusan akhir panitia.',
            'ditolak' => 'Tidak ada aksi lanjutan di portal ini untuk saat ini.',
            'registrasi_ulang' => 'Pendaftaran sudah ditutup dan data akhir telah masuk.',
            default => 'Tahap lanjutan akan muncul di portal ini sesuai progres panitia.',
        };
    }

    private function isEditableStatus(string $status): bool
    {
        return ! in_array($status, ['ditolak', 'registrasi_ulang'], true);
    }

    private function formatJenisKelamin(?string $value): string
    {
        return match ($value) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '',
        };
    }

    private function mapJenisKelamin(?string $value, ?string $fallback): ?string
    {
        return match ($value) {
            'Laki-laki' => 'L',
            'Perempuan' => 'P',
            default => $fallback,
        };
    }
}
