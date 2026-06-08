<?php

namespace Modules\PPDB\Services;

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
        $guardianPhone = $this->normalizePhone($candidate->no_hp_wali);
        $adminPhone = $this->normalizePhone((string) ($context['admin_whatsapp'] ?? ''), false);

        $registrationTemplate = $this->resolveTemplate(
            'albakids_registration_received',
            'Pendaftaran Alba Kids diterima',
            'Assalamu alaikum Bapak/Ibu {{nama_wali}}, terima kasih sudah mendaftarkan {{nama_calon}} di Alba Kids Pre-School. Nomor pendaftaran: {{nomor_pendaftaran}}. Bukti transfer sudah kami terima dan sedang diverifikasi. {{pesan_tambahan}}'
        );

        $adminTemplate = $this->resolveTemplate(
            'albakids_admin_new_registration',
            'Pendaftar baru Alba Kids',
            'Pendaftar baru Alba Kids masuk. Nama: {{nama_calon}}. Nomor: {{nomor_pendaftaran}}. Wali: {{nama_wali}} ({{no_hp_wali}}). Program: {{program_diminati}}. Segera cek admin untuk verifikasi bukti transfer.'
        );

        $result = [
            'guardian' => null,
            'admin' => null,
        ];

        $guardianBroadcast = $this->broadcastWaService->create([
            'template_id' => $registrationTemplate->id,
            'target_group' => 'custom',
            'filter_criteria' => [
                'targets' => [$guardianPhone],
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
                    'pesan_tambahan',
                ],
                'channel' => 'wa',
                'is_active' => true,
            ]
        );
    }

    private function normalizePhone(?string $value, bool $required = true): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($phone === '') {
            if ($required) {
                throw new InvalidArgumentException('Nomor WhatsApp tujuan notifikasi wajib diisi.');
            }

            return null;
        }

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }
}
