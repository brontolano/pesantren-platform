<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbFormFieldConfigurationService;
use Modules\PPDB\Services\PpdbRegistrationNumberService;

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

    public function store(
        Request $request,
        PpdbFormFieldConfigurationService $fieldConfigurationService,
        PpdbRegistrationNumberService $registrationNumberService
    )
    {
        $validated = $request->validate(
            $this->buildValidationRules($fieldConfigurationService->getConfiguration())
        );

        $nomorPendaftaran = $registrationNumberService->generate();
        $jenisKelamin = $validated['jenisKelamin'] === 'Laki-laki' ? 'L' : 'P';

        CalonSantri::create([
            'tenant_id' => tenant()?->getTenantKey(),
            'nomor_pendaftaran' => $nomorPendaftaran,
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
            'data_ortu' => [
                'nik' => $validated['nik'] ?? null,
                'jenjang_asal' => $validated['jenjangAsal'] ?? null,
                'hubungan' => $validated['hubunganWali'] ?? null,
                'catatan_tambahan' => $validated['catatanTambahan'] ?? null,
            ],
        ]);

        return back()->with('success', "Pendaftaran berhasil. Nomor pendaftaran Anda: {$nomorPendaftaran}");
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
}
