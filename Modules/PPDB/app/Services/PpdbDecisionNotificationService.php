<?php

namespace Modules\PPDB\Services;

use InvalidArgumentException;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\BroadcastWaService;
use Modules\PPDB\Models\CalonSantri;
use RuntimeException;

class PpdbDecisionNotificationService
{
    private const TEMPLATE_DEFINITIONS = [
        'lulus' => [
            'code' => 'ppdb-keputusan-lulus',
            'title' => 'Hasil PPDB {{nomor_pendaftaran}}',
            'body' => 'Assalamu alaikum {{nama_wali}}, calon santri {{nama_calon}} dengan nomor {{nomor_pendaftaran}} dinyatakan LULUS. {{catatan_keputusan}}{{tracking_line}}',
        ],
        'cadangan' => [
            'code' => 'ppdb-keputusan-cadangan',
            'title' => 'Hasil PPDB {{nomor_pendaftaran}}',
            'body' => 'Assalamu alaikum {{nama_wali}}, calon santri {{nama_calon}} dengan nomor {{nomor_pendaftaran}} saat ini berstatus CADANGAN. {{catatan_keputusan}}{{tracking_line}}',
        ],
        'ditolak' => [
            'code' => 'ppdb-keputusan-ditolak',
            'title' => 'Hasil PPDB {{nomor_pendaftaran}}',
            'body' => 'Assalamu alaikum {{nama_wali}}, calon santri {{nama_calon}} dengan nomor {{nomor_pendaftaran}} saat ini dinyatakan BELUM LULUS. {{catatan_keputusan}}{{tracking_line}}',
        ],
    ];

    public function __construct(
        private readonly BroadcastWaService $broadcastWaService,
        private readonly PpdbStatusTrackingService $trackingService,
    ) {
    }

    public function notifyDecision(CalonSantri|int $calonSantri): BroadcastWa
    {
        $this->ensureTenantContext();

        $candidate = $calonSantri instanceof CalonSantri
            ? CalonSantri::query()->findOrFail($calonSantri->getKey())
            : CalonSantri::query()->findOrFail($calonSantri);

        $status = $this->normalizeDecisionStatus($candidate->status_pendaftaran);
        $target = $this->normalizeTarget($candidate->no_hp_wali);
        $template = $this->resolveTemplate($status);
        $tracking = $this->trackingService->issueTrackingLink($candidate);

        $broadcast = $this->broadcastWaService->create([
            'template_id' => $template->id,
            'target_group' => 'custom',
            'filter_criteria' => [
                'targets' => [$target],
                'variables' => [
                    'nama_wali' => $this->resolveGuardianName($candidate),
                    'nama_calon' => $candidate->nama,
                    'nomor_pendaftaran' => $candidate->nomor_pendaftaran,
                    'status_ppdb' => $status,
                    'catatan_keputusan' => $this->resolveDecisionNote($candidate->catatan_keputusan),
                    'tracking_url' => $tracking['tracking_url'],
                    'tracking_line' => ' Pantau status di '.$tracking['tracking_url'],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->broadcastWaService->execute($broadcast);

        return $broadcast->fresh(['template', 'logs']);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk notifikasi keputusan PPDB.');
        }
    }

    private function normalizeDecisionStatus(?string $status): string
    {
        $normalized = trim(strtolower((string) $status));

        if (! array_key_exists($normalized, self::TEMPLATE_DEFINITIONS)) {
            throw new InvalidArgumentException('Notifikasi keputusan PPDB hanya boleh dikirim untuk status lulus, cadangan, atau ditolak.');
        }

        return $normalized;
    }

    private function normalizeTarget(?string $target): string
    {
        $normalized = trim((string) $target);

        if ($normalized === '') {
            throw new InvalidArgumentException('Nomor WhatsApp wali wajib ada sebelum notifikasi keputusan PPDB dikirim.');
        }

        return $normalized;
    }

    private function resolveTemplate(string $status): TemplateNotifikasi
    {
        $definition = self::TEMPLATE_DEFINITIONS[$status];

        return TemplateNotifikasi::query()->updateOrCreate(
            ['kode_template' => $definition['code']],
            [
                'judul' => $definition['title'],
                'isi' => $definition['body'],
                'variable_dinamis' => [
                    'nama_wali',
                    'nama_calon',
                    'nomor_pendaftaran',
                    'status_ppdb',
                    'catatan_keputusan',
                    'tracking_url',
                    'tracking_line',
                ],
                'channel' => 'wa',
                'is_active' => true,
            ]
        );
    }

    private function resolveGuardianName(CalonSantri $candidate): string
    {
        return $candidate->nama_ayah
            ?: $candidate->nama_ibu
            ?: 'Wali Santri';
    }

    private function resolveDecisionNote(?string $note): string
    {
        $normalized = trim((string) $note);

        return $normalized === '' ? '' : $normalized.' ';
    }
}
