<?php

namespace Modules\PPDB\Services;

use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\BroadcastWaService;
use Modules\PPDB\Models\CalonSantri;

class AlbaKidsRegistrationNotificationService
{
    public function __construct(
        private readonly BroadcastWaService $broadcastWaService,
    ) {
    }

    public function notify(CalonSantri $candidate, array $context = []): array
    {
        $guardianPhones = $this->guardianPhones($candidate);
        $adminPhone = $this->normalizePhone((string) ($context['admin_whatsapp'] ?? ''), false);

        $registrationTemplate = $this->resolveTemplate(
            'albakids_registration_received',
            'Pendaftaran Alba Kids diterima',
            'Assalamu alaikum Bapak/Ibu {{nama_wali}}, terima kasih sudah mendaftarkan {{nama_calon}} di Alba Kids Pre-School. Nomor pendaftaran: {{nomor_pendaftaran}}. Bukti transfer sudah kami terima dan sedang diverifikasi. {{pesan_tambahan}}'
        );

        $adminTemplate = $this->resolveTemplate(
            'albakids_admin_new_registration',
            'Pendaftar baru Alba Kids',
            "Pendaftar baru Alba Kids masuk.\nNama: {{nama_calon}}\nNomor: {{nomor_pendaftaran}}\nProgram: {{program_diminati}}\nAyah: {{nama_ayah}} ({{no_hp_ayah}})\nIbu: {{nama_ibu}} ({{no_hp_ibu}})\nAlamat: {{alamat}}\n\nDokumen (klik untuk lihat):\n{{dokumen}}\n\nSegera verifikasi di panel admin."
        );

        $this->resolveTemplate(
            'albakids_broadcast_general',
            'Broadcast Alba Kids',
            'Assalamu alaikum Bapak/Ibu, {{isi_pesan}}'
        );

        $result = [
            'guardian' => null,
            'admin' => null,
        ];

        $guardianBroadcast = $this->broadcastWaService->create([
            'template_id' => $registrationTemplate->id,
            'target_group' => 'custom',
            'filter_criteria' => [
                'targets' => $guardianPhones,
                'variables' => [
                    'nama_wali' => $candidate->nama_ayah ?: $candidate->nama_ibu ?: 'Bapak/Ibu Wali',
                    'nama_calon' => $candidate->nama,
                    'nomor_pendaftaran' => $candidate->nomor_pendaftaran,
                    'program_diminati' => $candidate->program_diminati,
                    'no_hp_wali' => $candidate->no_hp_wali,
                    'pesan_tambahan' => trim((string) ($context['success_message'] ?? '')),
                ],
            ],
            'status' => 'draft',
        ]);
        $this->broadcastWaService->execute($guardianBroadcast);
        $result['guardian'] = $guardianBroadcast->fresh(['logs']);

        if ($adminPhone !== null) {
            $adminBroadcast = $this->broadcastWaService->create([
                'template_id' => $adminTemplate->id,
                'target_group' => 'custom',
                'filter_criteria' => [
                    'targets' => [$adminPhone],
                    'variables' => [
                        'nama_wali' => $candidate->nama_ayah ?: $candidate->nama_ibu ?: 'Bapak/Ibu Wali',
                        'nama_calon' => $candidate->nama,
                        'nomor_pendaftaran' => $candidate->nomor_pendaftaran,
                        'program_diminati' => $candidate->program_diminati,
                        'no_hp_wali' => $candidate->no_hp_wali,
                        'nama_ayah' => $candidate->nama_ayah ?: '-',
                        'no_hp_ayah' => (string) (data_get($candidate->data_ortu, 'no_hp_ayah') ?: '-'),
                        'nama_ibu' => $candidate->nama_ibu ?: '-',
                        'no_hp_ibu' => (string) (data_get($candidate->data_ortu, 'no_hp_ibu') ?: '-'),
                        'alamat' => (string) ($candidate->alamat ?: '-'),
                        'dokumen' => $this->buildDocumentLinks($candidate),
                    ],
                ],
                'status' => 'draft',
            ]);
            $this->broadcastWaService->execute($adminBroadcast);
            $result['admin'] = $adminBroadcast->fresh(['logs']);
        }

        return $result;
    }

    private function resolveTemplate(string $code, string $title, string $body): TemplateNotifikasi
    {
        return TemplateNotifikasi::query()->updateOrCreate(
            ['kode_template' => $code],
            [
                'judul' => $title,
                'isi' => $body,
                'variable_dinamis' => [
                    'nama_wali',
                    'nama_calon',
                    'nomor_pendaftaran',
                    'program_diminati',
                    'no_hp_wali',
                    'nama_ayah',
                    'no_hp_ayah',
                    'nama_ibu',
                    'no_hp_ibu',
                    'alamat',
                    'dokumen',
                    'pesan_tambahan',
                    'isi_pesan',
                ],
                'channel' => 'wa',
                'is_active' => true,
            ]
        );
    }

    /**
     * Bangun daftar link unduh dokumen (bukti/kk/akta) sebagai signed URL,
     * untuk dilampirkan pada notifikasi WhatsApp admin.
     */
    private function buildDocumentLinks(CalonSantri $candidate): string
    {
        $docs = ['bukti' => 'Bukti Transfer'];
        if (data_get($candidate->data_ortu, 'dokumen.kk')) {
            $docs['kk'] = 'Kartu Keluarga';
        }
        if (data_get($candidate->data_ortu, 'dokumen.akta')) {
            $docs['akta'] = 'Akta Kelahiran';
        }

        $lines = [];
        foreach ($docs as $type => $label) {
            try {
                $url = URL::temporarySignedRoute('tenant.ppdb.signed-doc', now()->addDays(30), [
                    'candidate' => $candidate->getKey(),
                    'type' => $type,
                ]);
                $lines[] = "{$label}: {$url}";
            } catch (\Throwable $e) {
                // Route belum tersedia → lewati link, jangan gagalkan notifikasi.
            }
        }

        return $lines === [] ? '-' : implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function guardianPhones(CalonSantri $candidate): array
    {
        $phones = [
            $this->normalizePhone($candidate->no_hp_wali, false),
            $this->normalizePhone((string) data_get($candidate->data_ortu, 'no_hp_ayah', ''), false),
            $this->normalizePhone((string) data_get($candidate->data_ortu, 'no_hp_ibu', ''), false),
        ];

        $phones = array_values(array_unique(array_filter($phones)));

        if ($phones === []) {
            throw new InvalidArgumentException('Nomor WhatsApp tujuan notifikasi wajib diisi.');
        }

        return $phones;
    }

    private function normalizePhone(?string $value, bool $required = true): ?string
    {
        // Delegasi ke sumber kebenaran tunggal (idempoten, anti country-code ganda).
        $phone = \App\Support\PhoneNumber::normalize($value);

        if ($phone === '') {
            if ($required) {
                throw new InvalidArgumentException('Nomor WhatsApp tujuan notifikasi wajib diisi.');
            }

            return null;
        }

        return $phone;
    }
}
