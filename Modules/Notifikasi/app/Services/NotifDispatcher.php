<?php

namespace Modules\Notifikasi\Services;

use Illuminate\Support\Facades\Log;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use RuntimeException;
use Throwable;

/**
 * F7 — Notification Hooks.
 *
 * Satu titik masuk event → TemplateNotifikasi → channel (WA/email).
 * Modul lain (F1 approval, Kegiatan, PPDB, Inventory expiry, CRM) cukup
 * memanggil `NotifDispatcher::kirim($event, $payload)` alih-alih memanggil
 * gateway WA/email langsung. Fire-and-forget: TIDAK PERNAH melempar exception
 * ke pemanggil — kegagalan kanal dicatat ke log notifikasi.
 */
class NotifDispatcher
{
    /**
     * Registry event → kode_template default. Tenant boleh menimpa isi pesan
     * lewat TemplateNotifikasi dengan kode_template yang sama.
     */
    public const EVENTS = [
        'approval_chasing' => 'approval_chasing',     // F1: minta keputusan persetujuan
        'kegiatan_h1' => 'kegiatan_h1',               // Kegiatan: reminder H-1
        'tagihan_jatuh_tempo' => 'tagihan_jatuh_tempo', // Keuangan: tagihan jatuh tempo
        'stok_expiry' => 'stok_expiry',               // Inventory: batch mendekati kedaluwarsa
        'keputusan_ppdb' => 'keputusan_ppdb',         // PPDB: hasil seleksi
        // Event uang PWA Wali/Peserta (NOTIF-1, TGH-5, TAB-4) — additive.
        'pembayaran_diverifikasi' => 'PEMBAYARAN_DIVERIFIKASI', // wali: bukti diterima
        'pembayaran_ditolak' => 'PEMBAYARAN_DITOLAK',           // wali: bukti ditolak + alasan
        'topup_diverifikasi' => 'TOPUP_DIVERIFIKASI',           // wali: saldo bertambah
        'topup_ditolak' => 'TOPUP_DITOLAK',                     // wali: topup ditolak + alasan
        'bukti_bayar_masuk' => 'BUKTI_BAYAR_MASUK',             // admin: antrean verifikasi baru
        'berkas_ppdb_masuk' => 'BERKAS_PPDB_MASUK',             // admin: berkas/bukti PPDB baru
    ];

    public function __construct(
        private readonly WaNotificationService $waService,
        private readonly EmailNotificationService $emailService,
        private readonly RecipientResolver $resolver,
    ) {
    }

    /** Daftar event yang terdaftar. */
    public function eventTerdaftar(): array
    {
        return array_keys(self::EVENTS);
    }

    /** Resolusi template aktif untuk satu event (null bila tak ada/nonaktif). */
    public function resolveTemplate(string $event, ?string $override = null): ?TemplateNotifikasi
    {
        $kode = $override ?? self::EVENTS[$event] ?? $event;

        return TemplateNotifikasi::query()
            ->where('kode_template', $kode)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Kirim notifikasi untuk satu event. Fire-and-forget.
     *
     * @param  array{data?:array, wa?:array, email?:array, penerima?:array, target_group?:string, filter?:array, kode_template?:string}  $payload
     * @return array{event:string, channel:?string, terkirim:int, gagal:int, alasan?:string}
     */
    public function kirim(string $event, array $payload = []): array
    {
        $hasil = ['event' => $event, 'channel' => null, 'terkirim' => 0, 'gagal' => 0];

        try {
            $this->ensureTenantContext();

            $template = $this->resolveTemplate($event, $payload['kode_template'] ?? null);
            if (! $template) {
                return $hasil + ['alasan' => 'template_tidak_aktif'];
            }
            $hasil['channel'] = $template->channel;

            $varsGlobal = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $penerima = $this->resolvePenerima($payload);
            if (empty($penerima)) {
                return $hasil + ['alasan' => 'tanpa_penerima'];
            }

            if ($template->channel === 'email') {
                foreach ($penerima as $p) {
                    try {
                        $this->emailService->send($template, $p['target'], array_merge($varsGlobal, $p['variables']));
                        $hasil['terkirim']++;
                    } catch (Throwable $e) {
                        $hasil['gagal']++;
                        Log::warning('NotifDispatcher email gagal', ['event' => $event, 'error' => $e->getMessage()]);
                    }
                }

                return $hasil;
            }

            // Default channel = WA.
            $broadcast = BroadcastWa::create([
                'template_id' => $template->id,
                'target_group' => 'event:'.$event,
                'status' => 'processing',
                'tanggal_kirim' => now(),
                'created_by' => auth()->id(),
                'attempt_count' => count($penerima),
            ]);

            foreach ($penerima as $p) {
                $ok = $this->waService->sendToRecipient(
                    $broadcast, $template, $p['target'], array_merge($varsGlobal, $p['variables'])
                );
                $ok ? $hasil['terkirim']++ : $hasil['gagal']++;
            }

            $broadcast->update(['status' => $hasil['gagal'] === 0 ? 'selesai' : 'sebagian']);

            return $hasil;
        } catch (Throwable $e) {
            report($e);

            return $hasil + ['alasan' => 'exception'];
        }
    }

    /**
     * Normalisasi penerima → list of ['target'=>..., 'variables'=>[...]].
     * Dukung: eksplisit wa/email (string array), penerima [['target','variables']],
     * atau target_group + filter (lewat RecipientResolver).
     */
    private function resolvePenerima(array $payload): array
    {
        // 1) penerima eksplisit dalam bentuk struktur penuh
        if (! empty($payload['penerima']) && is_array($payload['penerima'])) {
            return collect($payload['penerima'])
                ->map(fn ($p) => is_array($p)
                    ? ['target' => $p['target'] ?? '', 'variables' => $p['variables'] ?? []]
                    : ['target' => (string) $p, 'variables' => []])
                ->filter(fn ($p) => filled($p['target']))
                ->values()->all();
        }

        // 2) daftar address sederhana (wa/email)
        $sederhana = array_merge((array) ($payload['wa'] ?? []), (array) ($payload['email'] ?? []));
        if (! empty($sederhana)) {
            return collect($sederhana)
                ->filter(fn ($t) => filled($t))
                ->map(fn ($t) => ['target' => (string) $t, 'variables' => []])
                ->values()->all();
        }

        // 3) target group via resolver
        if (! empty($payload['target_group'])) {
            return $this->resolver->resolve($payload['target_group'], $payload['filter'] ?? [])
                ->map(fn ($item) => is_array($item)
                    ? ['target' => $item['target'] ?? '', 'variables' => $item['variables'] ?? []]
                    : ['target' => (string) $item, 'variables' => []])
                ->filter(fn ($p) => filled($p['target']))
                ->values()->all();
        }

        return [];
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk notifikasi.');
        }
    }
}
