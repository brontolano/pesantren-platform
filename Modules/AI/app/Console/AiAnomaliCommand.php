<?php

namespace Modules\AI\Console;

use Illuminate\Console\Command;
use Modules\AI\Models\AiCreditLedger;
use Modules\Notifikasi\Models\LogNotifikasi;

/**
 * T-A002 — ai:anomali: alarm anomali biaya AI per tenant (TODO T-A003 ditebus).
 *
 * Sumber data = ai_credit_ledger CENTRAL (sumber kebenaran billing), jadi
 * perintah ini berjalan murni konteks central tanpa membuka DB tenant.
 * Anomali = kredit pemakaian HARI INI > faktor × rata-rata 7 hari sebelumnya
 * DAN melebihi ambang minimum absolut (hindari alarm palsu tenant sepi).
 * Notifikasi operator via pola log notifikasi database eksisting
 * (LogNotifikasi central, channel 'database') — sekali per tenant per hari.
 */
class AiAnomaliCommand extends Command
{
    protected $signature = 'ai:anomali';

    protected $description = 'Deteksi anomali biaya kredit AI per tenant (hari ini vs rata-rata 7 hari) dan beri tahu operator';

    public function handle(): int
    {
        $faktor = (float) config('ai.metering.anomali.faktor', 3.0);
        $minimum = (float) config('ai.metering.anomali.minimum_kredit', 100);
        $jumlahAnomali = 0;

        $awalHariIni = now()->startOfDay();

        // Pemakaian = jumlah_kredit negatif → pakai nilai absolut.
        $hariIniPerTenant = AiCreditLedger::query()
            ->where('jenis', AiCreditLedger::JENIS_PEMAKAIAN)
            ->where('created_at', '>=', $awalHariIni)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, sum(abs(jumlah_kredit)) as total')
            ->pluck('total', 'tenant_id');

        $baselinePerTenant = AiCreditLedger::query()
            ->where('jenis', AiCreditLedger::JENIS_PEMAKAIAN)
            ->where('created_at', '>=', $awalHariIni->copy()->subDays(7))
            ->where('created_at', '<', $awalHariIni)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, sum(abs(jumlah_kredit)) as total')
            ->pluck('total', 'tenant_id');

        foreach ($hariIniPerTenant as $tenantId => $totalHariIni) {
            $hariIni = (float) $totalHariIni;
            $baseline = (float) ($baselinePerTenant[$tenantId] ?? 0) / 7;

            // Anomali = hari ini > faktor × baseline DAN > minimum absolut.
            // Baseline 0 (tenant baru aktif) tetap anomali bila > minimum.
            if ($hariIni <= $minimum || $hariIni <= $baseline * $faktor) {
                continue;
            }

            // Sekali per tenant per hari — cek notifikasi anomali hari ini.
            $sudahAda = LogNotifikasi::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('jenis', 'ai_anomali')
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if ($sudahAda) {
                continue;
            }

            LogNotifikasi::query()->create([
                'tenant_id' => $tenantId,
                'jenis' => 'ai_anomali',
                'channel' => 'database',
                'target' => 'operator',
                'status' => 'sent',
                'payload' => [
                    'judul' => 'Anomali biaya AI terdeteksi',
                    'pesan' => sprintf(
                        'Pemakaian kredit AI tenant %s hari ini %.2f kredit, melebihi %.1f× '
                        .'rata-rata harian 7 hari terakhir (%.2f kredit). Mohon ditinjau.',
                        $tenantId,
                        $hariIni,
                        $faktor,
                        $baseline,
                    ),
                    'kredit_hari_ini' => $hariIni,
                    'baseline_harian' => round($baseline, 4),
                ],
                'created_at' => now(),
            ]);

            $this->warn("Anomali: tenant {$tenantId} — {$hariIni} kredit hari ini (baseline ".round($baseline, 2).').');
            $jumlahAnomali++;
        }

        $this->info("Pemeriksaan anomali biaya AI selesai: {$jumlahAnomali} anomali.");

        return self::SUCCESS;
    }
}
